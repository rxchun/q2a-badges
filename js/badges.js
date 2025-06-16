/* Q2A Badges */

document.addEventListener('DOMContentLoaded', () => {
	
	/**
	 * UI helper: Handle clicks on any .badge-count-link
	 * If Admin option is to not show sources, this class is not rendered in DOM
	 */
	document.body.addEventListener('click', (e) => {
		if (e.target && e.target.matches('.badge-count-link')) {
			const options = {
				badgeSlug: e.target.dataset.slug,
				type: e.target.dataset.typeSlug,
				name: e.target.dataset.name,
				popupTitle: e.target.dataset.popupTitle || null,
				desc: e.target.dataset.desc,
				fetchUrlBase: e.target.dataset.fetchUrl,
				userId: e.target.dataset.userid || 0,
			};

			loadBadgeSourceUsers(options);
			document.body.classList.add('no-scroll');
		}
	});
	
	// Create a hashmap to store fully retrieved content
	// so it displays a temporary cached version on the second click
	const badgeUserContentCache = new Map();
	
	/**
	 * Loads and displays the badge source users popup for a given badge.
	 * Creates the container if it doesn't exist, restores from cache if available,
	 * initializes lazy loading on scroll, and triggers the first load of badge entries.
	 *
	 * @param {Object} options - Badge info including slug, badge type, name, popupTitle, description, fetch URL, and user ID.
	 */
	const loadBadgeSourceUsers = ({
		badgeSlug,
		type,
		name,
		popupTitle,
		desc,
		fetchUrlBase,
		userId = 0
	}) => {
		let container = document.getElementById('badge-users-' + badgeSlug);
		const badgeLink = document.querySelector(`.badge-count-link[data-slug="${badgeSlug}"]`);
		if (!badgeLink) return;

		if (!container) {
			// Dynamically create the container and insert it after the .badge-count-link
			container = document.createElement('div');
			container.id = 'badge-users-' + badgeSlug;
			container.className = 'badge-container-sources';
			
			// If already cached, use it instead of rebuilding
			if (badgeUserContentCache.has(badgeSlug)) {
				// Restore from cache
				container.innerHTML = badgeUserContentCache.get(badgeSlug);
				badgeLink.parentElement.appendChild(container);
				
				// Reattach scroll listener and continue lazy load
				loadOnScroll(container, badgeSlug);
				
				// Spinner cleanup
				showLoadingSpinner(false);

				container.dataset.loaded = 'true'; // mark as loaded
				loadMoreBadgeEntries(badgeSlug); // Ensure user list is triggered
				container.classList.add('q2a-show-badge-source');
				return;
			}
			
			badgeLink.parentElement.appendChild(container);
		}

		if (!container.dataset.loaded) {
			
			const popupTitleCheck = popupTitle != null ? `<span class="bsh-title">${popupTitle}:</span>` : '';
			
			const htmlContent = `
				<div class="badge-source-container">
					<div class="badge-source-header flex flex-row">
						<div class="badge-source-info flex flex-column">
							<div class="bsh-container">
								${popupTitleCheck}
								<span class="badge-${type}">${name}</span>
								<span class="badge-source-title-description">${desc}</span>
							</div>
						</div>
						<div class="bsh-container">
							<div class="badge-close-btn flex noSelect">✕</div>
						</div>
					</div>
					<ul class="badge-sources-wrapper" data-slug="${badgeSlug}" data-offset="0" data-fetchurl="${fetchUrlBase}" data-userid="${userId}"></ul>
					<div class="badge-loading-spinner">
						<span class="badge-spinner">
							<div class="bubble-loader">
								<svg viewBox="0 0 120 30" width="60" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<circle class="dot" cx="15" cy="15" r="6" />
									<circle class="dot" cx="60" cy="15" r="6" />
									<circle class="dot" cx="105" cy="15" r="6" />
								</svg>
							</div>
						</span>
					</div>
				</div>
				<div class="badge-close-source noSelect"></div>
			`;

			container.innerHTML = htmlContent;
			container.dataset.loaded = 'true';
			
			loadMoreBadgeEntries(badgeSlug);
			
			// If reached the bottom of the element, load more badges
			loadOnScroll(container, badgeSlug);
		}
		
		container.classList.add('q2a-show-badge-source');
	};
	
	/**
	 * Fetches more badge entries asynchronously and appends them to the badge source list.
	 * Manages loading state, pagination offset, loading spinner, error handling,
	 * and caches fully loaded content for quick subsequent access.
	 *
	 * @param {string} badgeSlug - The unique badge identifier.
	 */
	const loadMoreBadgeEntries = badgeSlug => {
		const container = document.querySelector(`#badge-users-${badgeSlug} .badge-sources-wrapper`);
		if (!container) return;

		if (container.dataset.loading === 'true' || container.dataset.done === 'true') return;

		container.dataset.loading = 'true';

		const offset = parseInt(container.dataset.offset || '0', 10);
		const limit = 15;
		const fetchUrlBase = container.dataset.fetchurl;
		const userId = container.dataset.userid || 0;

		if (!fetchUrlBase) {
			console.error('Missing fetch URL base for badge:', badgeSlug);
			container.dataset.loading = 'false';
			return;
		}

		const fetchUrl = `${fetchUrlBase}?slug=${encodeURIComponent(badgeSlug)}&userid=${userId}&offset=${offset}&limit=${limit}`;
		// console.log(fetchUrl); // Uncomment for debug
		
		if (!badgeUserContentCache.has(badgeSlug))
			showLoadingSpinner(true);

		fetch(fetchUrl, {
			headers: {
				'X-Requested-With': 'Fetch'
			}
		})
			.then(res => {
				if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
				return res.text();
			})
			.then(html => {
				if (html.trim()) {
					container.insertAdjacentHTML('beforeend', html);
					container.dataset.offset = offset + limit;
				} else {
					container.dataset.done = 'true';

					// Cache the *fully loaded* badge content
					const wrapper = container.closest('.badge-container-sources');
					if (wrapper) {
						badgeUserContentCache.set(badgeSlug, wrapper.outerHTML);
					}
				}
			})
			.catch(err => {
				console.error('Failed to load more badge users:', err);
				showUserErrorMessage(container, 'Failed to load badge users. Please try again.');
			})
			.finally(() => {
				container.dataset.loading = 'false';

				// After content is loaded, check if scrollable and cache if not
				// Othewise it will keep calling short lists
				setTimeout(() => {
					const wrapper = document.querySelector(`.badge-container-sources`);
					if (wrapper) {
						const scrollContainer = wrapper.querySelector('.badge-sources-wrapper');
						
						// Ensure scrollContainer exists and check if it's scrollable
						if (scrollContainer && isNotScrollable(scrollContainer)) {
							// Cache the content as it's fully loaded
							container.dataset.loaded = 'true';
							badgeUserContentCache.set(badgeSlug, wrapper.outerHTML);
							// console.log('NOT scrollable');
							// console.log('cached');
						}
					}
				}, 260); // wait for the popup animations to finish, to get the full exapanded size (animation is .25s)
				
				setTimeout(() => {
					showLoadingSpinner(false);
				}, 500);

				if (typeof window.lazyLoadInstance !== 'undefined' && typeof window.lazyLoadInstance.update === 'function') {
					window.lazyLoadInstance.update();
				}

				badgeAdaptAvatar();
			});
	};
	
	// UI helper: show error message inside container
	const showUserErrorMessage = (container, message) => {
		if (!container) return;
		
		let errorElem = container.querySelector('.badge-error-message');
		if (!errorElem) {
			errorElem = document.createElement('div');
			errorElem.className = 'badge-error-message';
			container.appendChild(errorElem);
		}
		errorElem.textContent = message;
	};
	
	// UI helper: Load more Badges, on scroll
	const loadOnScroll = (container, badgeSlug) => {
		const scrollContainer = container.querySelector('.badge-sources-wrapper');
		if (scrollContainer) {
			scrollContainer.addEventListener('scroll', () => {
				if (scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 20) {
					loadMoreBadgeEntries(badgeSlug);
				}
			});
		}
	};
	
	/**
	 * Check if the element is scrollable.
	 * @param {HTMLElement} element - The DOM element to check.
	 * @return {boolean} - True if the element is scrollable, false otherwise.
	 */
	const isNotScrollable = element => {
		return element.scrollHeight <= element.clientHeight && element.scrollWidth <= element.clientWidth;
	};
	
	// UI helper: show/hide loading spinner
	const showLoadingSpinner = show => {
		document.querySelectorAll('.badge-loading-spinner').forEach(spinner => {
			spinner.classList.toggle('active', show);
		});
	};
	
	// UI helper: Badge Sources cleanup
	document.body.addEventListener('click', event => {
		if (
			event.target.matches('.badge-close-btn') ||
			event.target.matches('.badge-close-source')
		) {
			document.querySelectorAll('.badge-container-sources').forEach(container => container.remove());
			document.body.classList.remove('no-scroll');
		}
	});
	
	/**
	 * UI helper:
	 * Adds the class "wide-image" to avatar images whose width exceeds their height,
	 * enabling CSS-based styling for wide images to maintain aspect ratio consistency.
	 */
	const badgeAdaptAvatar = () => {
		const qaAvatarImages = document.querySelectorAll('.qa-avatar-image');

		qaAvatarImages.forEach((img) => {
			if (img.complete) {
				// If image already loaded, check now
				checkAndAddClass(img);
			} else {
				img.addEventListener('load', () => checkAndAddClass(img));
			}
		});

		function checkAndAddClass(img) {
			if (img.offsetWidth > img.offsetHeight) {
				img.classList.add('wide-image');
			}
		}
	};
	
	// UI helper: Scroll body to earned badge
	if (window.location.href.indexOf('badges') > -1) {
		const targetElement = document.querySelector('body.qa-template-user div.qa-part-form-badges-list');
		if (targetElement) {
			window.scrollTo({
				top: targetElement.getBoundingClientRect().top + window.pageYOffset,
				behavior: 'smooth'
			});
		}
	}
	
});
