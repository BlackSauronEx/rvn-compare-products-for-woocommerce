/**
 * RVN Compare — таблица сравнения: вкладки, характеристики и горизонтальная прокрутка.
 *
 * В HTML, отдаваемом кэшем, нет персональных ID. Скрипт получает их через
 * rvnCompare (или фиксированный products=) и запрашивает некэшируемый REST.
 * Никакие названия товара и описания атрибутов не вставляются через innerHTML.
 */
(function () {
	'use strict';

	var config = window.rvnCompareTableData;
	var core = window.rvnCompare;

	if (!config || !core) {
		return;
	}

	/** Создаёт DOM-элемент с классом и обычным текстом. */
	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) node.className = className;
		if (text !== undefined && text !== null) node.textContent = String(text);
		return node;
	}

	/** Проверяет адрес перед вставкой в ссылку; JavaScript-схемы запрещены. */
	function safeUrl(value) {
		try {
			var url = new URL(String(value), window.location.href);
			return (url.protocol === 'https:' || url.protocol === 'http:') ? url.href : '#';
		} catch (error) {
			return '#';
		}
	}

	/** Возвращает безопасные ID фиксированной таблицы из строки любого формата. */
	function fixedIds(root) {
		var raw = root.getAttribute('data-products') || '';
		var matches = raw.match(/\d+/g) || [];
		return matches.map(Number).filter(function (id, i, all) {
			return Number.isSafeInteger(id) && id > 0 && all.indexOf(id) === i;
		});
	}

	/** Сравнивает два списка ID без учёта порядка. */
	function sameIds(a, b) {
		if (!a || !b || a.length !== b.length) return false;
		var sA = a.slice().map(Number).sort();
		var sB = b.slice().map(Number).sort();
		for (var i = 0; i < sA.length; i++) {
			if (sA[i] !== sB[i]) return false;
		}
		return true;
	}

	/** Запоминает настройку свернутых групп между переходами по страницам. */
	function storageFor(root, name, fallback) {
		try {
			return window.localStorage.getItem('rvn-compare:table:' + name) || fallback;
		} catch (error) {
			return fallback;
		}
	}

	/** Сохраняет настройку свернутых групп без остановки таблицы при отказе браузера. */
	function saveStorage(name, value) {
		try {
			window.localStorage.setItem('rvn-compare:table:' + name, value);
		} catch (error) {
			// Приватный режим браузера может запретить localStorage.
		}
	}

	/** Создаёт состояние одной таблицы и подписывается на изменения общего списка. */
	function setup(root) {
		var fixed = fixedIds(root);
		var readonly = root.getAttribute('data-static') === '1';
		var state = {
			root: root,
			fixed: fixed,
			readonly: readonly,
			data: null,
			active: storageFor(root, 'active-tab', ''),
			collapsed: {},
			busy: false,
			generation: 0,
			syncing: false,
			diffOnly: false,
			reloadTimer: null,
			strips: []
		};

		root.dataset.static = readonly ? '1' : '0';
		root.dataset.scrollbar = config.scrollbar || 'hidden';

		var comparison = root.querySelector('[data-rvn-comparison]');
		if ('ResizeObserver' in window && comparison) {
			new ResizeObserver(function () {
				if (state.data) resizeColumns(state);
			}).observe(comparison);
		} else {
			window.addEventListener('resize', function () {
				if (state.data) resizeColumns(state);
			});
		}

		var toggle = root.querySelector('[data-rvn-differences]');
		if (toggle) {
			toggle.addEventListener('change', function () {
				state.diffOnly = toggle.checked;
				renderTab(state);
			});
		}

		root.querySelectorAll('[data-rvn-arrow]').forEach(function (button) {
			button.addEventListener('click', function () {
				moveOne(state, button.dataset.rvnArrow === 'next' ? 1 : -1);
			});
		});

		root.querySelector('[data-rvn-clear-all]')?.addEventListener('click', function () {
			if (window.confirm(config.i18n.confirmAll)) core.clear('', true);
		});

		root.querySelector('[data-rvn-clear-tab]')?.addEventListener('click', function () {
			if (state.active && window.confirm(config.i18n.confirmTab)) core.clear(state.active, true);
		});

		root.addEventListener('click', function (event) {
			var remove = event.target.closest('[data-rvn-table-remove]');
			if (remove && !readonly) {
				core.remove(Number(remove.dataset.rvnTableRemove), '');
			}
		});

		root.addEventListener('click', function (event) {
			if (!event.target.closest('[data-rvn-tooltip-button]') && !event.target.closest('[data-rvn-tooltip]')) hideTooltip(state);
		});

		window.addEventListener('scroll', function () { hideTooltip(state); }, { passive: true });
		window.addEventListener('resize', function () { hideTooltip(state); });
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') hideTooltip(state);
		});

		if (!readonly) {
			document.addEventListener('rvn-compare:list-updated', function (event) {
				if (!event.detail || !Array.isArray(event.detail.ids)) return;
				window.clearTimeout(state.reloadTimer);
				state.reloadTimer = window.setTimeout(function () { load(state, event.detail.ids); }, 90);
			});
		}

		// Ассеты таблицы могут загрузиться до ответа GET /list — запрашиваем
		// его ещё раз, чтобы пустое состояние аккаунта не считалось окончательным.
		if (readonly) {
			load(state, fixed);
		} else {
			core.load().then(function () { load(state, core.getIds()); });
		}
	}

	/** Запрашивает данные таблицы, отбрасывая опоздавшие ответы предыдущих запросов. */
	function load(state, ids) {
		// Если список не изменился (например, возврат на вкладку браузера), повторно не запрашиваем.
		if (state.data && Array.isArray(ids) && sameIds(state.data.ids, ids)) {
			return;
		}

		var generation = ++state.generation;
		var loading = state.root.querySelector('[data-rvn-loading]');
		// Не показываем плашку загрузки при обновлении данных, если таблица уже отрендерена —
		// это устраняет неприятный скачок контента вниз и вверх при переключении вкладок браузера.
		if (!state.data && loading) {
			loading.hidden = false;
		}
		var payload = state.readonly ? { products: state.fixed.join(',') } : { ids: (ids || []).join(',') };

		core.request('GET', '/table', payload).then(function (data) {
			if (generation !== state.generation) return;
			if (loading) loading.hidden = true;

			if (!data || !Array.isArray(data.tabs)) {
				if (!state.data && loading) {
					loading.hidden = false;
					loading.textContent = config.i18n.loadError;
				}
				return;
			}

			state.data = data;
			var available = data.tabs.some(function (tab) { return tab.key === state.active; });
			if (!available) state.active = data.tabs.length ? data.tabs[0].key : '';
			render(state);
		});
	}

	/** Обновляет пустое состояние и выбранную вкладку без перезагрузки. */
	function render(state) {
		var data = state.data;
		var root = state.root;
		var empty = root.querySelector('[data-rvn-table-empty]');
		var content = root.querySelector('[data-rvn-table-content]');
		var count = root.querySelector('[data-rvn-table-count]');
		var noProducts = !data.tabs.length;

		empty.hidden = !noProducts;
		content.hidden = noProducts;
		count.textContent = noProducts ? '' : '(' + data.count + ')';
		if (noProducts) return;

		renderTabs(state);
		renderTab(state);
	}

	/** Выводит вкладки категорий и обновляет aria-selected. */
	function renderTabs(state) {
		var wrapper = state.root.querySelector('[data-rvn-tabs]');
		wrapper.replaceChildren();

		state.data.tabs.forEach(function (tab) {
			var button = el('button', 'rvn-tab' + (tab.key === state.active ? ' is-active' : ''));
			button.type = 'button';
			button.setAttribute('role', 'tab');
			button.setAttribute('aria-selected', tab.key === state.active ? 'true' : 'false');
			button.dataset.tabKey = tab.key;
			button.appendChild(el('span', 'rvn-tab-name', tab.label));
			button.appendChild(el('span', 'rvn-tab-number', tab.count));
			button.addEventListener('click', function () {
				state.active = tab.key;
				state.root.dataset.activeTab = tab.key;
				saveStorage('active-tab', tab.key);
				renderTabs(state);
				renderTab(state);
			});
			wrapper.appendChild(button);
		});

		wrapper.setAttribute('role', 'tablist');
		wrapper.hidden = state.data.tabs.length < 2;
		wrapper.classList.toggle('is-many', state.data.tabs.length > 3);

		if (state.data.tabs.length > 3) {
			var select = el('select', 'rvn-tab-select');
			select.setAttribute('aria-label', config.i18n.selectCategory);
			state.data.tabs.forEach(function (tab) {
				var option = el('option', '', tab.label + ' (' + tab.count + ')');
				option.value = tab.key;
				option.selected = tab.key === state.active;
				select.appendChild(option);
			});
			select.addEventListener('change', function () {
				state.active = select.value;
				saveStorage('active-tab', state.active);
				renderTabs(state);
				renderTab(state);
			});
			wrapper.appendChild(select);
		}
	}

	/** Выводит колонки, строки и нижние кнопки активной категории. */
	function renderTab(state) {
		var tab = state.data.tabs.find(function (item) { return item.key === state.active; });
		if (!tab) return;
		state.root.dataset.activeTab = tab.key;
		state.strips = [];

		var toggle = state.root.querySelector('.rvn-table-diff-toggle');
		if (toggle) {
			toggle.hidden = !state.data.options.show_difference_only || tab.count < 2;
		}

		var clearTab = state.root.querySelector('[data-rvn-clear-tab]');
		var clearAll = state.root.querySelector('[data-rvn-clear-all]');
		var actions = state.root.querySelector('[data-rvn-actions]');
		if (clearTab) {
			clearTab.hidden = state.readonly || state.data.tabs.length < 2;
			clearTab.textContent = config.i18n.clearTab.replace('%s', tab.label);
		}
		if (clearAll) clearAll.hidden = state.readonly;
		if (actions) actions.hidden = state.readonly && toggle.hidden;

		var header = state.root.querySelector('[data-rvn-product-strip]');
		header.replaceChildren();
		tab.columns.forEach(function (product) { header.appendChild(productCard(product, state.readonly)); });
		registerStrip(state, header);

		var rows = state.root.querySelector('[data-rvn-rows]');
		rows.replaceChildren();
		var groups = {};
		state.data.groups.forEach(function (group) { groups[group.key] = group; });
		var previousGroup = '';
		var displayed = 0;

		tab.rows.forEach(function (field) {
			// Отключённая группа скрывает и заголовок, и её строки.
			if (config.groupsEnabled && groups[field.group]?.enabled === false) return;
			if (state.diffOnly && !field.different) return;
			displayed++;

			if (config.groupsEnabled && field.group !== previousGroup && groups[field.group]?.enabled !== false) {
				rows.appendChild(groupHeading(state, groups[field.group], field.group));
			}
			previousGroup = field.group;
			rows.appendChild(featureRow(state, field, tab, groups[field.group]));
		});

		if (!displayed) {
			rows.appendChild(el('p', 'rvn-table-no-differences', config.i18n.noDifferences));
		}

		var buyRow = state.root.querySelector('[data-rvn-buy-strip]');
		buyRow.replaceChildren();
		tab.columns.forEach(function (product) {
			var cell = el('div', 'rvn-buy-cell');
			cell.appendChild(buyButton(product, false));
			buyRow.appendChild(cell);
		});
		registerStrip(state, buyRow);

		// Все полосы видят одну и ту же ширину колонки и одинаковый scrollLeft.
		requestAnimationFrame(function () {
			resizeColumns(state);
			setScroll(state, 0);
		});
		updateProgress(state, tab);
	}

	/** Карточка товара в шапке таблицы. */
	function productCard(product, readonly) {
		var card = el('article', 'rvn-product-card');
		var link = el('a', 'rvn-product-image-link');
		link.href = safeUrl(product.url);
		var image = el('img', 'rvn-product-image');
		image.src = safeUrl(product.image);
		image.alt = String(product.title || '');
		image.loading = 'lazy';
		link.appendChild(image);
		card.appendChild(link);

		if (!readonly) {
			var remove = el('button', 'rvn-product-remove', '×');
			remove.type = 'button';
			remove.setAttribute('aria-label', config.i18n.remove + ': ' + product.title);
			remove.dataset.rvnTableRemove = product.id;
			card.appendChild(remove);
		}

		var title = el('a', 'rvn-product-name', product.title);
		title.href = safeUrl(product.url);
		card.appendChild(title);
		var offer = el('div', 'rvn-product-offer');
		offer.appendChild(el('span', 'rvn-product-price', product.price || '—'));
		offer.appendChild(buyButton(product, true));
		card.appendChild(offer);
		return card;
	}

	/** Добавляет покупку с учётом типа и наличия товара. */
	function buyButton(product, compact) {
		var button = product.can_buy ? el('a', 'rvn-product-buy' + (compact ? ' is-compact' : ''), product.buy_label) : el('span', 'rvn-product-buy is-disabled' + (compact ? ' is-compact' : ''), product.buy_label);
		if (product.can_buy) button.href = safeUrl(product.buy_url);
		return button;
	}

	/** Выводит заголовок группы с возможностью свернуть её строки. */
	function groupHeading(state, group, key) {
		var header = el('div', 'rvn-feature-group');
		var toggle = el('button', 'rvn-group-toggle');
		var collapsed = state.collapsed[key] ?? (storageFor(state.root, 'group:' + key, group?.collapsed ? '1' : '0') === '1');
		state.collapsed[key] = collapsed;
		toggle.type = 'button';
		toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		toggle.appendChild(el('span', 'rvn-group-chevron', '⌃'));
		toggle.appendChild(el('span', 'rvn-group-title', group?.label || ''));
		toggle.addEventListener('click', function () {
			state.collapsed[key] = !state.collapsed[key];
			saveStorage('group:' + key, state.collapsed[key] ? '1' : '0');
			state.root.querySelectorAll('[data-rvn-feature-group="' + key + '"]').forEach(function (row) {
				row.hidden = state.collapsed[key];
			});
			toggle.setAttribute('aria-expanded', state.collapsed[key] ? 'false' : 'true');
		});
		header.appendChild(toggle);
		return header;
	}

	/** Формирует строку характеристики: сверху закреплённая подпись, снизу значения товаров. */
	function featureRow(state, field, tab, group) {
		var row = el('div', 'rvn-feature' + (field.different && state.data.options.highlight_differences ? ' is-different' : ''));
		row.dataset.rvnFeatureGroup = field.group;
		row.hidden = config.groupsEnabled && group?.enabled !== false && !!state.collapsed[field.group];
		var caption = el('div', 'rvn-feature-caption');
		caption.appendChild(el('span', '', field.label));
		if (field.hint) caption.appendChild(tooltipButton(state, field.hint, field.label));
		row.appendChild(caption);

		var strip = el('div', 'rvn-table-strip rvn-feature-values');
		field.cells.forEach(function (cell) {
			var box = el('div', 'rvn-feature-value');
			if (cell.terms && cell.terms.length) {
				var lines = state.data.options.attribute_values === 'lines'
					|| (state.data.options.attribute_values === 'auto' && (cell.terms.length > 3 || cell.terms.some(function (item) { return !!item.description; })));
				var values = el('span', 'rvn-feature-terms' + (lines ? ' is-lines' : ''));
				cell.terms.forEach(function (item, index) {
					var term = el('span', 'rvn-feature-term');
					term.appendChild(el('span', '', item.label));
					if (item.description && state.data.options.term_tooltips) term.appendChild(tooltipButton(state, item.description, item.label));
					values.appendChild(term);
					if (!lines && index < cell.terms.length - 1) values.appendChild(document.createTextNode(', '));
				});
				box.appendChild(values);
			} else {
				box.textContent = cell.text || '—';
			}
			strip.appendChild(box);
		});
		row.appendChild(strip);
		registerStrip(state, strip);
		return row;
	}

	/** Кнопка подсказки: открывается клавиатурой, нажатием и наведением. */
	function tooltipButton(state, description, label) {
		var trigger = el('button', 'rvn-tooltip-trigger', '?');
		trigger.type = 'button';
		trigger.setAttribute('aria-label', config.i18n.tooltip + ': ' + label);
		trigger.dataset.rvnTooltipButton = '1';
		trigger.addEventListener('mouseenter', function () { showTooltip(state, trigger, description); });
		trigger.addEventListener('focus', function () { showTooltip(state, trigger, description); });
		trigger.addEventListener('mouseleave', function () { hideTooltip(state); });
		trigger.addEventListener('click', function (event) {
			event.stopPropagation();
			var box = state.root.querySelector('[data-rvn-tooltip]');
			if (box.hidden) showTooltip(state, trigger, description);
			else hideTooltip(state);
		});
		return trigger;
	}

	/** Выбирает сторону подсказки по реально свободному месту экрана. */
	function showTooltip(state, trigger, description) {
		var box = state.root.querySelector('[data-rvn-tooltip]');
		if (!box) return;
		box.textContent = description;
		box.hidden = false;
		box.style.top = '';
		box.style.left = '';
		var anchor = trigger.getBoundingClientRect();
		if (window.matchMedia('(max-width: 768px)').matches) return;
		var width = box.offsetWidth || 260;
		var height = box.offsetHeight || 60;
		var left = anchor.right + 10;
		if (left + width > window.innerWidth - 12) left = anchor.left - width - 10;
		if (left < 12) left = Math.max(12, Math.min(anchor.left, window.innerWidth - width - 12));
		var top = Math.min(Math.max(12, anchor.top - 8), window.innerHeight - height - 12);
		box.style.left = left + 'px';
		box.style.top = top + 'px';
	}

	/** Закрывает подсказку при Escape, прокрутке и клике снаружи. */
	function hideTooltip(state) {
		var box = state.root.querySelector('[data-rvn-tooltip]');
		if (box) box.hidden = true;
	}

	/** Регистрирует горизонтальную полосу и синхронизирует остальные полосы. */
	function registerStrip(state, strip) {
		state.strips.push(strip);
		strip.addEventListener('scroll', function () {
			if (state.syncing) return;
			state.syncing = true;
			var left = strip.scrollLeft;
			state.strips.forEach(function (peer) {
				if (peer !== strip && Math.abs(peer.scrollLeft - left) > 0.5) peer.scrollLeft = left;
			});
			state.syncing = false;
			updateArrows(state);
			hideTooltip(state);
		}, { passive: true });
	}

	/** Делит фактическую ширину таблицы на 5 / 3 / 2 колонки с учётом ширины экрана и контейнера. */
	function resizeColumns(state) {
		var comparison = state.root.querySelector('[data-rvn-comparison]');
		if (!comparison) return;
		var width = comparison.clientWidth;
		var screenWidth = window.innerWidth || width;
		var tablet = config.breakpoints.tablet || 1024;
		var phone = config.breakpoints.phone || 768;
		var target = screenWidth <= phone ? config.columns.phone : screenWidth <= tablet ? config.columns.tablet : config.columns.desktop;
		// В коробочных темах (Storefront) контент центрирован (~900-1000px).
		// Не сжимаем колонку уже 130px, но на десктопе выводим заданное число колонок (по умолчанию 5).
		var maxPossible = Math.max(1, Math.floor(width / 130));
		state.visible = Math.max(1, Math.min(Number(target) || 2, maxPossible));
		state.columnWidth = width / state.visible;
		state.root.style.setProperty('--rvn-col-width', state.columnWidth + 'px');
		updateArrows(state);
	}

	/** Ставит каждую полосу на один и тот же горизонтальный сдвиг. */
	function setScroll(state, left) {
		state.syncing = true;
		state.strips.forEach(function (strip) { strip.scrollLeft = left; });
		state.syncing = false;
		updateArrows(state);
	}

	/** Перемещает ровно на один товар, пока анимация не завершилась. */
	function moveOne(state, direction) {
		if (state.busy) return;
		var first = state.strips[0];
		if (!first) return;
		var maxScroll = Math.max(0, first.scrollWidth - first.clientWidth);
		if (maxScroll <= 1) return;
		var width = state.columnWidth || first.clientWidth / (state.visible || 2);
		var target = Math.max(0, Math.min(maxScroll, Math.round(first.scrollLeft / width + direction) * width));
		if (Math.abs(target - first.scrollLeft) < 0.5) return;
		var duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : Math.max(0, config.animation || 0);
		state.busy = duration > 0;
		state.strips.forEach(function (strip) { strip.scrollTo({ left: target, behavior: duration > 0 ? 'smooth' : 'instant' }); });
		window.setTimeout(function () { state.busy = false; setScroll(state, target); }, duration + 35);
	}

	/** Блокирует стрелку, если дальше нет целого товара, но оставляет её видимой на месте. */
	function updateArrows(state) {
		var first = state.strips[0];
		var prev = state.root.querySelector('[data-rvn-arrow="prev"]');
		var next = state.root.querySelector('[data-rvn-arrow="next"]');
		if (!first || !prev || !next) return;
		var max = Math.max(0, first.scrollWidth - first.clientWidth);
		var canScroll = max > 1;
		prev.disabled = !canScroll || first.scrollLeft <= 1;
		next.disabled = !canScroll || first.scrollLeft >= max - 1;
		state.root.classList.toggle('rvn-can-scroll', canScroll);
	}

	/** Обновляет шорткод «N из 12» для текущей вкладки. */
	function updateProgress(state, tab) {
		if (state.readonly) return;
		document.querySelectorAll('[data-rvn-progress]').forEach(function (node) {
			var limit = Number(window.rvnCompareData?.limits?.perCategory || 12);
			node.textContent = (node.dataset.template || '%1$d of %2$d').replace('%1$d', tab.count).replace('%2$d', limit);
		});
	}

	/** Запуск на каждой обнаруженной оболочке таблицы. */
	function start() {
		document.querySelectorAll('[data-rvn-table]').forEach(setup);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
