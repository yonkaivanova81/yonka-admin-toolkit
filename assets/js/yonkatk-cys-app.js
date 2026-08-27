/**
 * Vanilla JavaScript Application for Yearly Stats Module
 *
 * @package YonkaAdminToolkit
 * @internal
 */

/* global yonkatkCysVars */
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		const container = document.getElementById('yonkatk-cys-app');
		if (!container) {
			return;
		}
		// eslint-disable-next-line no-console
		console.log('yonkatk App: Initializing Vanilla JS interface...');

		let availableYears = [];
		let currentYear = '';
		let currentTab = 'posts';
		let statsData = null;

		// Base HTML structure using clean CSS classes
		container.innerHTML = `
            <div class="notice notice-info yonkatk-app-container">
                <div class="yonkatk-header">
                    <div>
                        <h2 class="yonkatk-title">Annual Site Statistics</h2>
                        <p class="yonkatk-subtitle">Select a year to review post categories, pages, and uploaded assets.</p>
                    </div>
                    <div class="yonkatk-controls">
                        <label class="yonkatk-label">Select Year:</label>
                        <select id="yonkatk-year-select" class="yonkatk-select"></select>
                    </div>
                </div>
                <div class="yonkatk-tabs" id="yonkatk-tabs">
                    <button type="button" class="button button-primary" data-tab="posts" id="yonkatk-tab-posts">📝 Posts (0)</button>
                    <button type="button" class="button button-secondary" data-tab="pages" id="yonkatk-tab-pages">📄 Pages (0)</button>
                    <button type="button" class="button button-secondary" data-tab="media" id="yonkatk-tab-media">📁 Media Files (0)</button>
                </div>
                <div id="yonkatk-content-area" class="yonkatk-content">Loading years...</div>
            </div>
        `;

		const yearSelect = document.getElementById('yonkatk-year-select');
		const contentArea = document.getElementById('yonkatk-content-area');
		const tabsContainer = document.getElementById('yonkatk-tabs');

		// 1. Fetch available years
		const initialFormData = new FormData();
		initialFormData.append('action', 'yonkatk_cys_get_years');
		initialFormData.append('nonce', yonkatkCysVars.nonce);

		fetch(yonkatkCysVars.ajax_url, {
			method: 'POST',
			body: initialFormData,
		})
			.then((r) => r.json())
			.then((res) => {
				if (res.success && res.data && res.data.length > 0) {
					availableYears = res.data;
					currentYear = String(availableYears[0]);

					yearSelect.innerHTML = availableYears
						.map((y) => `<option value="${y}">${y}</option>`)
						.join('');
					yearSelect.value = currentYear;

					fetchStats(currentYear);
				} else {
					contentArea.innerHTML = 'No statistics data found.';
				}
			})
			.catch((err) => {
				// eslint-disable-next-line no-console
				console.error('yonkatk App Error:', err);
				contentArea.innerHTML = 'Error loading years.';
			});

		// 2. Year change event
		yearSelect.addEventListener('change', function () {
			currentYear = this.value;
			fetchStats(currentYear);
		});

		// 3. Tabs click events
		tabsContainer.addEventListener('click', function (e) {
			const btn = e.target.closest('button[data-tab]');
			if (!btn) {
				return;
			}

			currentTab = btn.getAttribute('data-tab');

			tabsContainer.querySelectorAll('button').forEach((b) => {
				b.className = 'button button-secondary';
			});
			btn.className = 'button button-primary';

			renderContent();
		});

		// 4. Fetch statistics for selected year
		function fetchStats(year) {
			contentArea.innerHTML =
				'<div class="yonkatk-loading">Loading statistics for ' +
				year +
				'...</div>';

			const statsFormData = new FormData();
			statsFormData.append('action', 'yonkatk_cys_get_stats_by_year');
			statsFormData.append('nonce', yonkatkCysVars.nonce);
			statsFormData.append('year', year);

			fetch(yonkatkCysVars.ajax_url, {
				method: 'POST',
				body: statsFormData,
			})
				.then((r) => r.json())
				.then((res) => {
					// eslint-disable-next-line no-console
					console.log('yonkatk Stats Response:', res);
					if (res.success) {
						statsData = res.data;
						updateTabCounts();
						renderContent();
					} else {
						contentArea.innerHTML =
							'<div class="yonkatk-error">Failed to load statistics.</div>';
					}
				})
				.catch((err) => {
					// eslint-disable-next-line no-console
					console.error('yonkatk App Error:', err);
					contentArea.innerHTML =
						'<div class="yonkatk-error">Error loading statistics.</div>';
				});
		}

		function updateTabCounts() {
			if (!statsData) {
				return;
			}
			document.getElementById('yonkatk-tab-posts').textContent =
				`📝 Posts (Total: ${statsData.total_posts || 0})`;
			document.getElementById('yonkatk-tab-pages').textContent =
				`📄 Pages (Total: ${statsData.total_pages || 0})`;
			const totalMedia = statsData.media_summary
				? statsData.media_summary.total
				: 0;
			document.getElementById('yonkatk-tab-media').textContent =
				`📁 Media Files (Total: ${totalMedia})`;
		}

		function renderContent() {
			if (!statsData) {
				return;
			}

			let html = '';

			if (currentTab === 'posts') {
				html += `<h3>Posts Published by Category in ${currentYear}</h3>`;
				if (
					!statsData.posts_by_cat ||
					statsData.posts_by_cat.length === 0
				) {
					html += `<p class="red">No posts were published in ${currentYear}.</p>`;
				} else {
					html += `<ul class="yonkatk-list">`;
					statsData.posts_by_cat.forEach((cat) => {
						html += `<li><strong>${cat.category_name}</strong>: ${cat.count} posts</li>`;
					});
					html += `</ul>`;
				}
			} else if (currentTab === 'pages') {
				html += `<h3>Pages Created in ${currentYear} (Total: ${statsData.total_pages || 0})</h3>`;
				if (!statsData.pages || statsData.pages.length === 0) {
					html += `<p class="red">No pages were created in ${currentYear}.</p>`;
				} else {
					html += `<ul class="yonkatk-list">`;
					statsData.pages.forEach((page) => {
						const title = page.post_title
							? page.post_title
							: '(No Title)';
						const date = page.post_date
							? page.post_date.split(' ')[0]
							: '';
						html += `<li>${title} - ${date}</li>`;
					});
					html += `</ul>`;
				}
			} else if (currentTab === 'media') {
				const ms = statsData.media_summary || {
					images: 0,
					documents: 0,
					audio: 0,
					video: 0,
					others: 0,
					total: 0,
				};
				html += `<h3>Media Assets Uploaded in ${currentYear} (Total: ${ms.total})</h3>`;
				html += `<ul class="yonkatk-list">
                    <li>🖼️ Images: <strong>${ms.images}</strong></li>
                    <li>📄 Documents: <strong>${ms.documents}</strong></li>
                    <li>🎵 Audio: <strong>${ms.audio}</strong></li>
                    <li>🎬 Video: <strong>${ms.video}</strong></li>
                    <li>📦 Others: <strong>${ms.others}</strong></li>
                </ul>`;
			}

			contentArea.innerHTML = html;
		}
	});
})();
