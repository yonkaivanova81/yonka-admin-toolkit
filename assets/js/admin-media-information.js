/**
 * Media Inventory Module Script
 *
 * @package YonkaAdminToolkit
 */

/* global yonkatkMoData */
(function () {
	let rawData       = null;
	let currentTab    = 'images';
	let currentFilter = 'all';

	// Fetch media statistics via AJAX.
	function loadData() {
		const formData = new FormData();
		formData.append( 'action', 'yonkatk_get_media_stats' );
		formData.append( 'nonce', yonkatkMoData.nonce );

		fetch(
			yonkatkMoData.ajaxurl,
			{
				method: 'POST',
				body: formData,
			}
		)
			.then(
				function (r) {
					return r.json();
				}
			)
			.then(
				function (res) {
					if (res.success) {
						rawData = res.data;
						renderStats();
						renderTable();
					} else {
						document.getElementById( 'yonkatk-mo-loading' ).innerHTML =
						'<span style="color:#d63638;">' +
						yonkatkMoData.i18n.errorLoading +
						'</span>';
					}
				}
			)
			.catch(
				function (err) {
					document.getElementById( 'yonkatk-mo-loading' ).innerHTML =
					'<span style="color:#d63638;">' +
					yonkatkMoData.i18n.ajaxError +
					err.message +
					'</span>';
				}
			);
	}

	// Populate summary counters on top of the page.
	function renderStats() {
		document.getElementById( 'stat-count' ).innerText     =
			rawData.summary.total_count;
		document.getElementById( 'stat-size' ).innerText      =
			rawData.summary.total_size;
		document.getElementById( 'stat-oversized' ).innerText =
			rawData.summary.total_oversized;
		document.getElementById( 'stat-alt' ).innerText       =
			rawData.summary.total_missing_alt;

		document.getElementById( 'count-images' ).innerText    =
			rawData.items.images.length;
		document.getElementById( 'count-documents' ).innerText =
			rawData.items.documents.length;
		document.getElementById( 'count-media' ).innerText     =
			rawData.items.media.length;
		document.getElementById( 'count-others' ).innerText    =
			rawData.items.others.length;
	}

	// Switch active media category tab.
	window.yonkatkSwitchTab = function (tab) {
		currentTab    = tab;
		currentFilter = 'all';

		const tabs = document.querySelectorAll( '.yonkatk-mo-tab' );
		tabs.forEach(
			function (t) {
				t.classList.remove( 'nav-tab-active' );
			}
		);

		const activeTab = document.querySelector(
			'.yonkatk-mo-tab[data-tab="' + tab + '"]'
		);
		if (activeTab) {
			activeTab.classList.add( 'nav-tab-active' );
		}

		const altBtn = document.getElementById( 'btn-filter-alt' );
		if (altBtn) {
			altBtn.style.display = tab === 'images' ? 'inline-block' : 'none';
		}

		renderTable();
	};

	// Apply quick filter (e.g. oversized or missing ALT).
	window.yonkatkApplyFilter = function (filter) {
		currentFilter = filter;
		renderTable();
	};

	// Helper function to pick an emoji icon based on file MIME type.
	function getFileIcon(mime, tab) {
		if (mime.indexOf( 'video/' ) === 0) {
			return '🎬';
		}
		if (mime.indexOf( 'audio/' ) === 0) {
			return '🎵';
		}
		if (
			tab === 'documents' ||
			mime.indexOf( 'application/' ) === 0 ||
			mime.indexOf( 'text/' ) === 0
		) {
			return '📄';
		}
		if (tab === 'images' || mime.indexOf( 'image/' ) === 0) {
			return '🖼️';
		}
		return '📦';
	}

	// Build dynamic markup for the data table.
	function renderTable() {
		if ( ! rawData) {
			return;
		}

		const items    = rawData.items[currentTab] || [];
		const filtered = items.filter(
			function (item) {
				if (currentFilter === 'oversized') {
					return item.is_oversized;
				}
				if (currentFilter === 'missing_alt') {
					return currentTab === 'images' && ! item.has_alt;
				}
				return true;
			}
		);

		const tbody     = document.getElementById( 'yonkatk-mo-tbody' );
		tbody.innerHTML = '';

		if (filtered.length === 0) {
			const trEmpty       = document.createElement( 'tr' );
			const tdEmpty       = document.createElement( 'td' );
			tdEmpty.colSpan     = 5;
			tdEmpty.className   = 'yonkatk-mo-empty-row';
			tdEmpty.textContent = yonkatkMoData.i18n.noFiles;
			trEmpty.appendChild( tdEmpty );
			tbody.appendChild( trEmpty );
		} else {
			filtered.forEach(
				function (item) {
					const tr = document.createElement( 'tr' );

					// Preview column.
					const previewTd     = document.createElement( 'td' );
					previewTd.className = 'yonkatk-mo-td';

					if (item.thumb_url) {
						const img     = document.createElement( 'img' );
						img.src       = item.thumb_url;
						img.className = 'yonkatk-mo-preview-img';
						previewTd.appendChild( img );
					} else {
						const div       = document.createElement( 'div' );
						div.className   = 'yonkatk-mo-preview-fallback';
						div.textContent = getFileIcon( item.mime, currentTab );
						previewTd.appendChild( div );
					}

					// Title & meta details column.
					const titleTd     = document.createElement( 'td' );
					titleTd.className = 'yonkatk-mo-td';

					const titleDiv       = document.createElement( 'div' );
					titleDiv.className   = 'yonkatk-mo-item-title';
					titleDiv.textContent = item.title;

					const metaDiv       = document.createElement( 'div' );
					metaDiv.className   = 'yonkatk-mo-item-meta';
					metaDiv.textContent = item.mime + ' | ' + item.date;

					titleTd.appendChild( titleDiv );
					titleTd.appendChild( metaDiv );

					// File size column.
					const sizeTd     = document.createElement( 'td' );
					sizeTd.className = 'yonkatk-mo-td yonkatk-mo-size-cell';
					if (item.is_oversized) {
						sizeTd.classList.add( 'oversized' );
					}
					sizeTd.textContent = item.size_formatted;

					// Flags column (Oversized, ALT status).
					const flagsTd     = document.createElement( 'td' );
					flagsTd.className = 'yonkatk-mo-td';

					if (item.is_oversized) {
						const spanOver       = document.createElement( 'span' );
						spanOver.className   = 'yonkatk-mo-badge-oversized';
						spanOver.textContent = '> 1MB';
						flagsTd.appendChild( spanOver );
					}

					if (currentTab === 'images') {
						const spanAlt = document.createElement( 'span' );
						if (item.has_alt) {
							spanAlt.className   = 'yonkatk-mo-badge-alt-ok';
							spanAlt.textContent = 'ALT OK';
						} else {
							spanAlt.className   = 'yonkatk-mo-badge-no-alt';
							spanAlt.textContent = yonkatkMoData.i18n.noAlt;
						}
						flagsTd.appendChild( spanAlt );
					}

					// Actions column.
					const actionTd           = document.createElement( 'td' );
					actionTd.className       = 'yonkatk-mo-td';
					actionTd.style.textAlign = 'right';

					const editLink       = document.createElement( 'a' );
					editLink.href        = item.edit_url;
					editLink.target      = '_blank';
					editLink.className   = 'button button-small';
					editLink.textContent = yonkatkMoData.i18n.editLink;
					actionTd.appendChild( editLink );

					tr.appendChild( previewTd );
					tr.appendChild( titleTd );
					tr.appendChild( sizeTd );
					tr.appendChild( flagsTd );
					tr.appendChild( actionTd );

					tbody.appendChild( tr );
				}
			);
		}

		document.getElementById( 'yonkatk-mo-loading' ).style.display = 'none';
		document
			.getElementById( 'yonkatk-mo-table' )
			.classList.remove( 'yonkatk-mo-hidden' );
	}

	// Initialize load event.
	if (document.readyState === 'loading') {
		document.addEventListener( 'DOMContentLoaded', loadData );
	} else {
		loadData();
	}
})();
