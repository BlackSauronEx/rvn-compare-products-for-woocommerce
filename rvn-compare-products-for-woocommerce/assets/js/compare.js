/**
 * RVN Compare — логика страниц магазина.
 *
 * Скрипт хранит список сравнения, обновляет все кнопки и счётчики, показывает
 * уведомления и синхронизирует вкладки. Он же отвечает за работу на страницах
 * с кэшем: список подгружается поверх готовой страницы, поэтому персональные
 * данные не попадают в общий кэш.
 *
 * Данные из PHP приходят в объекте rvnCompareData.
 */
(function () {
	'use strict';

	var data = window.rvnCompareData || {};

	if (!data.restUrl) {
		return;
	}

	var STORAGE_KEY = data.storageKey || 'rvn-compare:list';
	// Разные сайты одной сети могут иметь общий origin, но не общий список товаров.
	var CHANNEL = STORAGE_KEY + ':channel';
	var state = {
		ids: [],
		source: 'guest',
		channel: null
	};
	var elements = {
		buttons: [],
		counters: [],
		progress: []
	};

	/* ---------------------------------------------------------------- утилиты */

	/** Безопасно читает список из localStorage. */
	function readStored() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			var parsed = raw ? JSON.parse(raw) : [];

			return Array.isArray(parsed) ? parsed.map(Number).filter(function (id) { return id > 0; }) : [];
		} catch (error) {
			// Приватный режим или переполнение хранилища: работаем без списка.
			return [];
		}
	}

	/** Безопасно сохраняет список в localStorage. */
	function writeStored(ids) {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
		} catch (error) {
			// Сохранить не удалось — список продолжит работать в текущей вкладке.
		}
	}

	/** Убирает повторы и мусор из списка. */
	function uniqueIds(ids) {
		return ids.map(Number).filter(function (id, index, list) {
			return id > 0 && list.indexOf(id) === index;
		});
	}

	/** Проверяет, есть ли товар в списке. */
	function has(id) {
		return state.ids.indexOf(Number(id)) !== -1;
	}

	/** Возвращает безопасный для разметки текст шаблона уведомления. */
	function formatText(template, values) {
		var text = String(template || '');

		values.forEach(function (value, index) {
			text = text.replace(new RegExp('%' + (index + 1) + '\\$[sd]|%[sd]'), String(value));
		});

		return text;
	}

	/* ------------------------------------------------------ обращения к серверу */

	/**
	 * Получает свежий REST nonce без передачи просроченного ключа.
	 *
	 * REST сам отвергает просроченный ключ ещё до вызова нашего маршрута,
	 * поэтому нужен некэшируемый read-only admin-ajax.php. Одновременные
	 * запросы пользуются одним обновлением ключа.
	 *
	 * @return {Promise<string>} Новый nonce текущей сессии.
	 */
	var pendingNonceRefresh = null;

	function refreshNonce() {
		if (pendingNonceRefresh) {
			return pendingNonceRefresh;
		}

		if (!data.nonceRefreshUrl) {
			return Promise.reject(new Error('Nonce refresh URL is missing'));
		}

		var separator = data.nonceRefreshUrl.indexOf('?') === -1 ? '?' : '&';
		var url = data.nonceRefreshUrl + separator + '_rvn=' + Date.now();

		pendingNonceRefresh = window.fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			}
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('Nonce refresh returned HTTP ' + response.status);
			}

			return response.json();
		}).then(function (body) {
			if (!body || body.success !== true || !body.data || typeof body.data.nonce !== 'string' || !body.data.nonce) {
				throw new Error('Nonce refresh returned invalid data');
			}

			data.nonce = body.data.nonce;
			data.isUser = body.data.user === 'registered';

			return data.nonce;
		}).finally(function () {
			pendingNonceRefresh = null;
		});

		return pendingNonceRefresh;
	}

	/**
	 * Выполняет запрос к REST API плагина.
	 *
	 * @param {string} method  Метод запроса.
	 * @param {string} path    Путь внутри пространства имён.
	 * @param {Object} payload Параметры запроса.
	 * @param {boolean} noRetry Не повторять запрос при устаревшем ключе.
	 * @return {Promise<Object>} Промис с телом ответа.
	 */
	function request(method, path, payload, noRetry) {
		var url = data.restUrl + path;
		var options = {
			method: method,
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.nonce || ''
			},
			credentials: 'same-origin',
			cache: 'no-store'
		};

		if (method === 'GET' && payload) {
			url += (url.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams(payload).toString();
		} else if (payload) {
			options.body = JSON.stringify(payload);
		}

		return window.fetch(url, options).then(function (response) {
			var freshNonce = response.headers.get('X-WP-Nonce');

			if (freshNonce) {
				data.nonce = freshNonce;
			}

			return response.json().catch(function () {
				return {};
			}).then(function (body) {
				var invalidNonce = body.code === 'rest_cookie_invalid_nonce' || body.code === 'rvn_compare_rest_forbidden';

				if (response.status === 403 && invalidNonce && !noRetry) {
					return refreshNonce().then(function () {
						return request(method, path, payload, true);
					});
				}

				if (!response.ok && method !== 'GET') {
					toast('requestError', {});
				}

				return body;
			});
		}).catch(function () {
			// Никакого ложного оптимистического обновления: при ошибке список не меняется.
			if (method !== 'GET') {
				toast('requestError', {});
			}

			return { status: 'request_error' };
		});
	}

	/* -------------------------------------------------------------- состояние */

	/**
	 * Применяет проверенное состояние и сообщает другим вкладкам только об изменениях.
	 *
	 * GET /list не должен рассылать состояние снова: иначе две вкладки
	 * авторизованного пользователя бесконечно читают списки друг у друга.
	 * @param {Object} response Ответ сервера.
	 * @param {boolean} notifyPeers Успешное изменение нужно объявить другим вкладкам.
	 */
	function applyState(response, notifyPeers) {
		if (!response || !Array.isArray(response.ids)) {
			return;
		}

		state.ids = uniqueIds(response.ids);
		state.source = response.user === 'registered' ? 'user' : 'guest';

		if (state.source === 'guest') {
			writeStored(state.ids);
		}

		render();
		document.dispatchEvent(new CustomEvent('rvn-compare:list-updated', {
			detail: { ids: state.ids.slice(), source: state.source, status: response.status }
		}));

		if (notifyPeers === true) {
			broadcast();
		}
	}

	/** Загружает состояние с сервера с учётом роли посетителя. */
	function load() {
		var payload = state.source === 'guest' || !data.isUser ? { ids: readStored().join(',') } : null;

		return request('GET', '/list', payload).then(applyState);
	}

	/**
	 * Добавляет товар в список.
	 *
	 * @param {number} id    ID товара.
	 * @param {string} title Название для уведомления.
	 */
	function add(id, title) {
		request('POST', '/items', { product_id: id, ids: readStored().join(',') }).then(function (response) {
			applyState(response, response.status === 'added');

			if (response.status === 'added') {
				// Отмена добавления — это удаление: направление задаём явно.
				toast('added', { title: title, undo: id, undoAction: 'remove' });
			} else if (response.status === 'already') {
				// Ничего не показываем: состояние уже совпадает с ожиданием.
				return;
			} else if (response.status === 'limit_category') {
				toast('limitTab', {
					text: formatText(data.i18n.limitTab, [
						response.tab && response.tab.label ? response.tab.label : '',
						response.tab && response.tab.count ? response.tab.count : 0,
						response.tab && response.tab.limit ? response.tab.limit : 0
					])
				});
			} else if (response.status === 'limit_total') {
				toast('limitTotal', {
					text: formatText(data.i18n.limitTotal, [
						response.limits ? response.limits.total : 0,
						response.limits ? response.limits.total : 0
					])
				});
			} else if (response.status === 'not_found') {
				toast('unavailable', {});
			}
		});
	}

	/**
	 * Удаляет товар из списка.
	 *
	 * @param {number} id    ID товара.
	 * @param {string} title Название для уведомления.
	 */
	function remove(id, title) {
		return request('POST', '/items/remove', { product_id: id, ids: readStored().join(',') }).then(function (response) {
			if (response.status !== 'removed') {
				return response;
			}

			applyState(response, true);
			toast('removed', { title: title, undo: id });
			return response;
		});
	}

	/**
	 * Очищает весь список или одну вкладку; требует подтверждения покупателя.
	 *
	 * @param {string} tabKey Ключ активной категории либо пустая строка для всего списка.
	 * @param {boolean} confirmed Таблица уже получила подтверждение сама.
	 * @return {Promise<Object>} Результат действия.
	 */
	function clear(tabKey, confirmed) {
		if (confirmed !== true && !window.confirm(tabKey ? (data.i18n.confirmTab || 'Clear this category?') : (data.i18n.confirmAll || 'Clear all?'))) {
			return Promise.resolve({ status: 'cancelled' });
		}

		return request('POST', '/clear', { ids: readStored().join(','), tab: tabKey || '' }).then(function (response) {
			if (response.status === 'cleared') {
				applyState(response, true);
				toast('cleared', {});
			}

			return response;
		});
	}

	/* --------------------------------------------------------------- отрисовка */

	/** Собирает ссылки на элементы сравнения на странице. */
	function collect() {
		elements.buttons = Array.prototype.slice.call(document.querySelectorAll('[data-rvn-compare-button]'));
		elements.counters = Array.prototype.slice.call(document.querySelectorAll('[data-rvn-counter]'));
		elements.progress = Array.prototype.slice.call(document.querySelectorAll('[data-rvn-progress]'));
	}

	/** Приводит кнопку в нужное состояние. */
	function renderButton(button, active, mode) {
		var id = Number(button.getAttribute('data-product-id'));
		var label = active ? button.getAttribute('data-label-in-list') : button.getAttribute('data-label-compare');

		button.classList.toggle('is-active', active);
		button.classList.toggle('rvn-mode-' + mode, true);
		button.setAttribute('aria-pressed', active ? 'true' : 'false');
		button.setAttribute('aria-label', label || '');

		Array.prototype.forEach.call(button.querySelectorAll('.rvn-button-text'), function (node) {
			node.textContent = label || '';
		});

		button.setAttribute('data-product-id', String(id));
	}

	/** Применяет текущее состояние ко всем элементам страницы. */
	function render() {
		var touch = isTouch();

		elements.buttons.forEach(function (button) {
			var id = Number(button.getAttribute('data-product-id'));
			var desktop = button.getAttribute('data-mode-desktop') || '';
			var mobile = button.getAttribute('data-mode-mobile') || 'inherit';
			var mode = touch && mobile && mobile !== 'inherit' ? mobile : desktop;

			if (mode) {
				button.className = button.className.replace(/rvn-mode-[a-z_]+/g, '').trim() + ' rvn-mode-' + mode;
			}

			renderButton(button, has(id), mode || 'icon_text');
		});

		elements.counters.forEach(function (node) {
			node.textContent = String(state.ids.length);
		});

		elements.progress.forEach(function (node) {
			var limit = (data.limits && data.limits.perCategory) || 0;
			var current = Math.min(state.ids.length, limit);

			node.textContent = formatText(node.getAttribute('data-template') || '%1$d of %2$d', [current, limit]);
		});

		document.documentElement.classList.toggle('rvn-has-items', state.ids.length > 0);
		document.documentElement.classList.toggle('rvn-is-empty', state.ids.length === 0);
	}

	/** Устройство с сенсорным вводом. */
	function isTouch() {
		return window.matchMedia('(hover: none)').matches;
	}

	/* ------------------------------------------------------------- уведомления */

	/** Возвращает контейнер уведомлений для нужной позиции. */
	function toastHost() {
		var mobile = window.matchMedia('(max-width: 767px)').matches;
		var position = mobile && data.toastPositionMobile ? data.toastPositionMobile : (data.toastPosition || 'bottom_right');
		var host = document.querySelector('.rvn-toasts[data-position="' + position + '"]');

		if (!host) {
			host = document.createElement('div');
			host.className = 'rvn-toasts';
			host.setAttribute('data-position', position);
			host.setAttribute('aria-live', 'polite');
			host.setAttribute('aria-atomic', 'false');
			host.setAttribute('data-rvn-nonce-placeholder', '');
			document.body.appendChild(host);
		}

		return host;
	}

	/**
	 * Показывает уведомление.
	 *
	 * @param {string} kind   Ключ текста уведомления.
	 * @param {Object} params Дополнительные данные: text, undo.
	 */
	function toast(kind, params) {
		params = params || {};

		var text = params.text || data.i18n[kind] || '';

		if (params.title && text.indexOf('%s') === -1) {
			text = text.replace(/%1\$s|%s/, params.title);
		}

		if (!text) {
			return;
		}

		var host = toastHost();
		var duration = Number(data.toastDuration || 3200);
		var node = document.createElement('div');

		node.className = 'rvn-toast';
		node.setAttribute('role', 'status');

		var message = document.createElement('span');
		message.className = 'rvn-toast-text';
		message.textContent = text;
		node.appendChild(message);

		if (params.undo) {
			var undo = document.createElement('button');
			undo.type = 'button';
			undo.className = 'rvn-toast-undo';
			undo.textContent = data.i18n.undo || 'Undo';
			undo.addEventListener('click', function () {
				if (params.undoAction === 'remove') {
					remove(Number(params.undo), params.title || '');
				} else {
					add(Number(params.undo), params.title || '');
				}

				dismiss(node);
			});
			node.appendChild(undo);
		}

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'rvn-toast-close';
		close.setAttribute('aria-label', data.i18n.close || 'Close');
		close.textContent = '\u00d7';
		close.addEventListener('click', function () {
			dismiss(node);
		});
		node.appendChild(close);

		if (duration > 0) {
			var bar = document.createElement('span');
			bar.className = 'rvn-toast-bar';
			bar.style.animationDuration = duration + 'ms';
			node.appendChild(bar);
		}

		host.appendChild(node);

		while (host.children.length > 3) {
			host.removeChild(host.firstChild);
		}

		if (duration > 0) {
			// Наведение останавливает отсчёт: остаётся время на чтение и «Отменить».
			var remaining = duration;
			var started = Date.now();
			var timer = 0;

			var pause = function () {
				if (!timer) {
					return;
				}

				window.clearTimeout(timer);
				timer = 0;
				remaining = Math.max(remaining - (Date.now() - started), 0);
				node.classList.add('is-paused');
			};

			var resume = function () {
				if (node.isConnected && !timer) {
					started = Date.now();
					timer = window.setTimeout(function () {
						timer = 0;
						dismiss(node);
					}, remaining);
					node.dataset.timeout = String(timer);
					node.classList.remove('is-paused');
				}
			};

			node.addEventListener('mouseenter', pause);
			node.addEventListener('mouseleave', resume);

			timer = window.setTimeout(function () {
				timer = 0;
				dismiss(node);
			}, duration);
			node.dataset.timeout = String(timer);
		}
	}

	/** Закрывает уведомление с анимацией. */
	function dismiss(node) {
		if (!node || !node.parentNode) {
			return;
		}

		window.clearTimeout(Number(node.dataset.timeout || 0));
		node.classList.add('is-leaving');
		window.setTimeout(function () {
			if (node.parentNode) {
				node.parentNode.removeChild(node);
			}
		}, 180);
	}

	/* --------------------------------------------------- синхронизация вкладок */

	/** Сообщает другим вкладкам об успешном изменении. */
	function broadcast() {
		if (!state.channel) {
			return;
		}

		try {
			// Списки аккаунтов нельзя передавать между вкладками: там может быть
			// другой вошедший пользователь. Передаём только сигнал перечитать сервер.
			state.channel.postMessage(
				state.source === 'user'
					? { source: 'user', changed: true }
					: { source: 'guest', ids: state.ids }
			);
		} catch (error) {
			// Канал может быть закрыт — это не мешает работе страницы.
		}
	}

	/** Принимает сигнал или гостевой список из другой вкладки. */
	function receive(payload) {
		if (!payload || payload.source !== state.source) {
			return;
		}

		if (state.source === 'user' && payload.changed === true) {
			// load() не рассылает сигнал повторно — бесконечного цикла нет.
			load();
			return;
		}

		if (state.source === 'guest' && Array.isArray(payload.ids)) {
			state.ids = uniqueIds(payload.ids);
			render();
			document.dispatchEvent(new CustomEvent('rvn-compare:list-updated', {
				detail: { ids: state.ids.slice(), source: 'guest', status: 'synced' }
			}));
		}
	}

	/* -------------------------------------------------------------- действия */

	/** Находит название товара для уведомления. */
	function productTitle(id) {
		var button = document.querySelector('[data-rvn-compare-button][data-product-id="' + id + '"]');

		if (button) {
			var card = button.closest('.product, li.product, .wc-block-grid__product');
			var heading = card ? card.querySelector('.woocommerce-loop-product__title, h2, h3') : null;

			if (heading) {
				return heading.textContent.trim();
			}
		}

		return '';
	}

	/** Навешивает обработчики на кнопки, счётчики и кнопки очистки. */
	function bind() {
		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-rvn-compare-button]');

			if (button) {
				event.preventDefault();
				var id = Number(button.getAttribute('data-product-id'));

				if (has(id)) {
					remove(id, productTitle(id));
				} else {
					add(id, productTitle(id));
				}

				return;
			}

			var clearButton = event.target.closest('[data-rvn-clear]');

			if (clearButton) {
				event.preventDefault();
				var tabKey = clearButton.dataset.scope === 'tab'
					? (clearButton.closest('[data-rvn-table]')?.dataset.activeTab || '')
					: '';
				clear(tabKey, false);
			}
		}, false);
	}

	/**
	 * Готовит кнопки поверх изображения.
	 *
	 * Кнопка не должна оказаться внутри ссылки на товар (иначе клик открывает
	 * карточку), а её родитель обязан быть позиционирован — тогда положение
	 * получается одинаковым в любой теме.
	 */
	function prepareOverlays() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-rvn-overlay]'), function (slot) {
			var link = slot.closest('a');

			if (link && link.parentNode) {
				link.parentNode.insertBefore(slot, link.nextSibling);
			}

			// В классических темах слот лежит рядом с галереей товара: переносим
			// его внутрь контейнера изображения, тогда все четыре угла точны.
			var host = slot.closest('.woocommerce-product-gallery, .images, .product-image, .wp-block-woocommerce-product-image, figure');

			if (host && host.nodeType === 1) {
				if (window.getComputedStyle(host).position === 'static') {
					host.style.position = 'relative';
				}

				host.appendChild(slot);
				return;
			}

			var parent = slot.parentNode;

			if (parent && parent.nodeType === 1 && window.getComputedStyle(parent).position === 'static') {
				parent.style.position = 'relative';
			}
		});
	}

	/** Слияние гостевого списка со списком аккаунта при входе. */
	function mergeOnLogin() {
		if (!data.isUser) {
			return;
		}

		var stored = readStored();

		if (!stored.length) {
			return;
		}

		request('POST', '/merge', { ids: stored.join(',') }).then(function (response) {
			// Ошибка nonce, сети или превышение частоты не должны стирать список гостя.
			if (response.status !== 'merged') {
				return;
			}

			applyState(response, true);
			writeStored([]);

			if (response.added > 0) {
				toast('merged', { text: formatText(data.i18n.merged, [response.added]) });
			}
		});
	}

	/** Подписывается на события синхронизации. */
	function listen() {
		window.addEventListener('storage', function (event) {
			if (event.key === STORAGE_KEY && state.source === 'guest') {
				state.ids = readStored();
				render();
				document.dispatchEvent(new CustomEvent('rvn-compare:list-updated', {
					detail: { ids: state.ids.slice(), source: 'guest', status: 'synced' }
				}));
			}
		});

		if ('BroadcastChannel' in window) {
			try {
				state.channel = new BroadcastChannel(CHANNEL);
				state.channel.addEventListener('message', function (event) {
					receive(event.data);
				});
			} catch (error) {
				state.channel = null;
			}
		}

		// Возврат на вкладку и восстановление страницы из кэша браузера.
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden && state.source === 'user') {
				load();
			}
		});

		window.addEventListener('pageshow', function (event) {
			if (event.persisted) {
				load();
			}
		});
	}

	/** Запуск. */
	function start() {
		// Сразу фиксируем роль, чтобы синхронизация вкладок до первого REST-ответа
		// не обработала авторизованную вкладку как гостевую.
		state.source = data.isUser ? 'user' : 'guest';
		state.ids = data.isUser ? [] : readStored();

		collect();
		prepareOverlays();
		bind();
		listen();
		render();

		load().then(function () {
			mergeOnLogin();
		});

		// Пересборка после подгрузки товаров (бесконечная прокрутка, AJAX-фильтры).
		document.addEventListener('rvn-compare:changed', function () {
			collect();
			prepareOverlays();
			render();
		});
	}

	/** Публичный API для таблицы и интеграций без прямого доступа к изменяемому состоянию. */
	window.rvnCompare = {
		getIds: function () { return state.ids.slice(); },
		getSource: function () { return state.source; },
		request: request,
		remove: remove,
		clear: clear,
		load: load
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
