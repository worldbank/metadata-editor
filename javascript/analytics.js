/**
 * Analytics Library
 * Client-side analytics tracking
 * 
 * Features:
 * - Session tracking (sessionStorage)
 * - Browser fingerprinting (localStorage)
 * - Auto-tracking: page views, performance, errors, idle, scroll
 * - Manual event tracking API
 * - Beacon API for reliability
 * 
 * @version 1.0
 */
(function(window, document) {
    'use strict';

    const Analytics = {
        
        config: {
            enabled: true,           // Enable/disable analytics tracking
            apiEndpoint: null,       // Will be set via init() or auto-detected
            siteUrl: null,           // Base site URL to strip from paths
            trackHashChanges: false  // Track hash (#) changes in URLs
        },
        
        isEnabled: function() {
            return this.config.enabled === true;
        },
        
        /**
         * Get or create session ID
         */
        getSessionId: function() {
            let sessionId = sessionStorage.getItem('analytics_session_id');
            
            if (!sessionId) {
                // Generate new session ID
                sessionId = this.generateSessionId();
                sessionStorage.setItem('analytics_session_id', sessionId);
                sessionStorage.setItem('analytics_session_start', Date.now().toString());
                
                // Track session start
                this.trackEvent('session_start', {
                    entry_page: window.location.pathname,
                    referrer: document.referrer || '(direct)',
                    device_type: this.getDeviceType(),
                    screen_resolution: screen.width + 'x' + screen.height,
                    viewport_size: window.innerWidth + 'x' + window.innerHeight,
                    browser: this.getBrowserInfo(),
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
                });
            }
            
            return sessionId;
        },
        
        /**
         * Generate unique session ID
         * Format: timestamp_random_browserId
         */
        generateSessionId: function() {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            const browserId = this.getBrowserId().substring(0, 8);
            return timestamp + '_' + random + '_' + browserId;
        },
        
        /**
         * Get or create persistent browser ID
         * This persists across sessions to track returning users
         */
        getBrowserId: function() {
            let browserId = localStorage.getItem('analytics_browser_id');
            
            if (!browserId) {
                browserId = this.generateBrowserId();
                localStorage.setItem('analytics_browser_id', browserId);
            }
            
            return browserId;
        },
        
        /**
         * Generate browser fingerprint
         */
        generateBrowserId: function() {
            const components = [
                navigator.userAgent,
                navigator.language,
                screen.colorDepth,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                navigator.hardwareConcurrency || 'unknown',
                navigator.platform
            ];
            
            const fingerprint = components.join('|');
            return this.hashCode(fingerprint) + '_' + Date.now();
        },
        
        /**
         * Simple hash function
         */
        hashCode: function(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return Math.abs(hash).toString(36);
        },
        
        /**
         * Get device type
         */
        getDeviceType: function() {
            const ua = navigator.userAgent;
            if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
                return 'tablet';
            }
            if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
                return 'mobile';
            }
            return 'desktop';
        },
        
        /**
         * Get browser info
         */
        getBrowserInfo: function() {
            const ua = navigator.userAgent;
            let browser = 'Unknown';
            
            if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
            else if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) browser = 'Opera';
            else if (ua.indexOf('Trident') > -1) browser = 'IE';
            else if (ua.indexOf('Edg') > -1) browser = 'Edge';
            else if (ua.indexOf('Chrome') > -1) browser = 'Chrome';
            else if (ua.indexOf('Safari') > -1) browser = 'Safari';
            
            return browser;
        },
        
        /**
         * Get OS info
         */
        getOSInfo: function() {
            const ua = navigator.userAgent;
            let os = 'Unknown';
            
            if (ua.indexOf('Windows') > -1) os = 'Windows';
            else if (ua.indexOf('Mac OS X') > -1) os = 'macOS';
            else if (ua.indexOf('Linux') > -1) os = 'Linux';
            else if (ua.indexOf('Android') > -1) os = 'Android';
            else if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) os = 'iOS';
            
            return os;
        },
        
        /**
         * Get compact user agent string
         * Format: {Browser}-{OS}-{Device}
         * Example: Chrome-macOS-desktop
         */
        getCompactUserAgent: function() {
            const browser = this.getBrowserInfo();
            const os = this.getOSInfo();
            const device = this.getDeviceType();
            
            return browser + '-' + os + '-' + device;
        },
        
        /**
         * Get clean page path (removes site_url base from path)
         */
        getCleanPage: function() {
            let pathname = window.location.pathname;
            let hash = window.location.hash || '';
            
            // If siteUrl is configured, strip it from the path
            if (this.config.siteUrl) {
                try {
                    const baseUrl = new URL(this.config.siteUrl);
                    const basePath = baseUrl.pathname;
                    
                    // Remove base path from pathname
                    if (basePath && basePath !== '/' && pathname.startsWith(basePath)) {
                        pathname = pathname.substring(basePath.length);
                    }
                } catch (e) {
                    // If URL parsing fails, keep original pathname
                }
            }
            
            // Remove /index.php if present
            pathname = pathname.replace(/\/index\.php/, '');
            
            // Remove query parameters (everything after ?)
            pathname = pathname.split('?')[0];
            
            // Ensure pathname starts with /
            if (!pathname || !pathname.startsWith('/')) {
                pathname = '/' + (pathname || '');
            }
            
            return pathname + hash;
        },
        
        /**
         * Track an event
         */
        trackEvent: function(eventType, data) {
            // Check if analytics is enabled
            if (!this.isEnabled()) {
                return false;
            }
            
            data = data || {};
            
            const sessionId = this.getSessionId();
            const browserId = this.getBrowserId();
            
            const eventData = {
                session_id: sessionId,
                browser_id: browserId,
                event_type: eventType,
                page: this.getCleanPage(),
                user_agent: this.getCompactUserAgent(),  // Compact: Browser-OS-Device
                data: data
                // No timestamp - server will set created_at automatically
            };
            
            // Send to server
            this.sendToServer(eventData);
        },
        
        /**
         * Send event to server
         */
        sendToServer: function(eventData) {
            // Use Beacon API for reliability (works even when page is closing)
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(eventData)], {
                    type: 'application/json'
                });
                navigator.sendBeacon(this.config.apiEndpoint, blob);
            } else {
                // Fallback to fetch with keepalive
                fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(eventData),
                    keepalive: true
                }).catch(function(err) {
                    console.error('Analytics error:', err);
                });
            }
        },
        
        /**
         * Track page view
         * No data stored - page column has all needed information
         */
        trackPageView: function() {
            // No data needed - page column contains full path + hash
            this.trackEvent('page_view', null);
            
            // Store page entry time for calculating time on page
            sessionStorage.setItem('analytics_page_entry', Date.now().toString());
        },
        
        /**
         * Track time on page
         */
        getTimeOnPage: function() {
            const entryTime = sessionStorage.getItem('analytics_page_entry');
            if (entryTime) {
                return Math.floor((Date.now() - parseInt(entryTime)) / 1000);
            }
            return 0;
        },
        
        /**
         * Track session end
         */
        trackSessionEnd: function() {
            const sessionStart = sessionStorage.getItem('analytics_session_start');
            const duration = sessionStart ? 
                Math.floor((Date.now() - parseInt(sessionStart)) / 1000) : 0;
            
            this.trackEvent('session_end', {
                duration: duration,
                exit_page: window.location.pathname,
                time_on_page: this.getTimeOnPage()
            });
        },
        
        /**
         * Track search/filter event
         */
        trackSearch: function(searchData) {
            searchData = searchData || {};
            
            // Remove empty values
            const cleanData = {};
            for (const key in searchData) {
                if (searchData.hasOwnProperty(key) && searchData[key] !== null && searchData[key] !== '' && searchData[key] !== undefined) {
                    cleanData[key] = searchData[key];
                }
            }
            
            // Only track if there's actual search data
            if (Object.keys(cleanData).length > 0) {
                this.trackEvent('search', cleanData);
            }
        },
        
        /**
         * Track click event
         */
        trackClick: function(element, eventData) {
            eventData = eventData || {};
            
            const data = {
                element_tag: element.tagName.toLowerCase(),
                element_id: element.id || null,
                element_class: element.className || null,
                element_text: element.textContent ? element.textContent.substring(0, 100) : null
            };
            
            // Merge additional data
            for (const key in eventData) {
                if (eventData.hasOwnProperty(key)) {
                    data[key] = eventData[key];
                }
            }
            
            this.trackEvent('click', data);
        },
        
        /**
         * Track form submission
         */
        trackFormSubmit: function(form, eventData) {
            eventData = eventData || {};
            
            const data = {
                form_id: form.id || null,
                form_name: form.name || null,
                form_action: form.action || null,
                fields_count: form.elements.length
            };
            
            // Merge additional data
            for (const key in eventData) {
                if (eventData.hasOwnProperty(key)) {
                    data[key] = eventData[key];
                }
            }
            
            this.trackEvent('form_submit', data);
        },
        
        /**
         * Track error
         */
        trackError: function(error, eventData) {
            eventData = eventData || {};
            
            const data = {
                error_type: 'javascript',
                message: error.message || error,
                file: error.filename || null,
                line: error.lineno || null,
                column: error.colno || null,
                stack: error.error && error.error.stack ? error.error.stack : null
            };
            
            // Merge additional data
            for (const key in eventData) {
                if (eventData.hasOwnProperty(key)) {
                    data[key] = eventData[key];
                }
            }
            
            this.trackEvent('error', data);
        },
        
        /**
         * Track slow pages (only if load time > 5 seconds)
         */
        trackSlowPage: function() {
            if (!window.performance || !window.performance.timing) {
                return;
            }
            
            const perfData = performance.timing;
            const loadTime = perfData.loadEventEnd - perfData.navigationStart;
            
            // Only track if page took more than 5 seconds to load
            if (loadTime > 5000) {
                const data = {
                    load_time: loadTime,
                    dom_ready: perfData.domContentLoadedEventEnd - perfData.navigationStart,
                    request_time: perfData.responseEnd - perfData.requestStart,
                    response_time: perfData.responseEnd - perfData.responseStart,
                    dom_processing: perfData.domComplete - perfData.domLoading
                };
                
                this.trackEvent('slow_page', data);
            }
        },
        
        /**
         * Track performance (manual - tracks all pages)
         */
        trackPerformance: function() {
            if (!window.performance || !window.performance.timing) {
                return;
            }
            
            const perfData = performance.timing;
            const loadTime = perfData.loadEventEnd - perfData.navigationStart;
            
            // Only track if page has finished loading
            if (loadTime > 0) {
                const data = {
                    load_time: loadTime,
                    dom_ready: perfData.domContentLoadedEventEnd - perfData.navigationStart,
                    dns_time: perfData.domainLookupEnd - perfData.domainLookupStart,
                    tcp_time: perfData.connectEnd - perfData.connectStart,
                    request_time: perfData.responseEnd - perfData.requestStart,
                    response_time: perfData.responseEnd - perfData.responseStart,
                    dom_processing: perfData.domComplete - perfData.domLoading
                };
                
                this.trackEvent('performance', data);
            }
        },
        
        /**
         * Setup universal URL change tracking
         * Works with hash routing, history API, and any SPA framework
         */
        setupUrlChangeTracking: function() {
            const self = this;
            
            // Intercept pushState (used by most SPA routers)
            const originalPushState = history.pushState;
            history.pushState = function() {
                originalPushState.apply(this, arguments);
                if (self.isEnabled()) {
                    self.trackPageView();
                }
            };
            
            // Intercept replaceState
            const originalReplaceState = history.replaceState;
            history.replaceState = function() {
                originalReplaceState.apply(this, arguments);
                if (self.isEnabled()) {
                    self.trackPageView();
                }
            };
            
            // Listen for popstate (back/forward buttons)
            window.addEventListener('popstate', function() {
                if (self.isEnabled()) {
                    self.trackPageView();
                }
            });
            
            // Listen for hashchange (hash-based routing like Vue Router)
            // Only track if trackHashChanges is enabled
            if (this.config.trackHashChanges) {
                window.addEventListener('hashchange', function() {
                    if (self.isEnabled()) {
                        const hash = window.location.hash;
                        
                        // Only track if hash has actual content after #/
                        // Skip: #, #/, or empty hash
                        if (hash && hash !== '#' && hash !== '#/') {
                            self.trackPageView();
                        }
                    }
                });
            }
        },
        
        /**
         * Detect API endpoint from various sources
         */
        detectApiEndpoint: function() {
            // Method 1: Check for global CI variable
            if (typeof CI !== 'undefined' && CI.base_url) {
                return CI.base_url + '/api/analytics/track';
            }
            
            // Method 2: Check for base tag
            const baseTag = document.querySelector('base');
            if (baseTag && baseTag.href) {
                return baseTag.href + 'api/analytics/track';
            }
            
            // Method 3: Use current origin and path
            const origin = window.location.origin;
            const pathname = window.location.pathname;
            const basePath = pathname.substring(0, pathname.indexOf('/', 1)) || '';
            return origin + basePath + '/api/analytics/track';
        },
        
        /**
         * Initialize analytics
         * @param {Object} options - Configuration options
         * @param {boolean} options.enabled - Enable/disable analytics (default: true)
         * @param {string} options.siteUrl - Base site URL (e.g., 'https://yourdomain.com/nada')
         */
        init: function(options) {
            options = options || {};
            
            // Set enabled state
            if (typeof options.enabled !== 'undefined') {
                this.config.enabled = options.enabled === true;
            }
            
            // If disabled, don't initialize further
            if (!this.isEnabled()) {
                console.log('Analytics is disabled');
                return;
            }
            
            // Set site URL
            if (options.siteUrl) {
                this.config.siteUrl = options.siteUrl;
                // Build API endpoint from siteUrl
                this.config.apiEndpoint = options.siteUrl + '/api/analytics/track';
            } else {
                // Auto-detect if siteUrl not provided
                this.config.apiEndpoint = this.detectApiEndpoint();
            }
            
            // Set trackHashChanges option
            if (typeof options.trackHashChanges !== 'undefined') {
                this.config.trackHashChanges = options.trackHashChanges === true;
            }
            
            // Track initial page view
            this.trackPageView();
            
            // Setup universal URL change tracking
            this.setupUrlChangeTracking();
            
            // Setup event listeners
            if (window.addEventListener) {
                // Track page load performance (all pages)
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        Analytics.trackPerformance();
                    }, 0);
                });
                
                // Track slow pages (> 5 seconds) - separate event for slow pages
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        Analytics.trackSlowPage();
                    }, 0);
                });
                
                // Session end tracking - DISABLED
                // Tab visibility tracking - DISABLED
                // Idle tracking - DISABLED
                // Scroll depth tracking - DISABLED
                
                // Track errors
                window.addEventListener('error', function(e) {
                    Analytics.trackError(e);
                });
                
                // Track unhandled promise rejections
                window.addEventListener('unhandledrejection', function(e) {
                    Analytics.trackError({
                        message: e.reason && e.reason.message ? e.reason.message : 'Unhandled Promise Rejection',
                        stack: e.reason && e.reason.stack ? e.reason.stack : null
                    }, {
                        error_type: 'promise_rejection'
                    });
                });
            }
        },
        
        /**
         * Setup idle time tracking
         * DISABLED by default - call manually if needed
         */
        setupIdleTracking: function() {
            let idleTime = 0;
            let isIdle = false;
            
            const resetIdleTime = function() {
                if (isIdle && idleTime >= Analytics.config.idleThreshold) {
                    Analytics.trackEvent('user_active', {
                        idle_duration: idleTime
                    });
                    isIdle = false;
                }
                idleTime = 0;
            };
            
            // Reset on user activity
            const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
            for (let i = 0; i < events.length; i++) {
                if (document.addEventListener) {
                    document.addEventListener(events[i], resetIdleTime, {passive: true});
                }
            }
            
            // Check idle every minute
            setInterval(function() {
                idleTime++;
                if (idleTime >= Analytics.config.idleThreshold && !isIdle) {
                    Analytics.trackEvent('user_idle', {
                        idle_duration: idleTime
                    });
                    isIdle = true;
                }
            }, 60000);  // 1 minute
        },
        
        /**
         * Setup scroll depth tracking
         * DISABLED by default - call manually if needed
         */
        setupScrollTracking: function() {
            let maxScroll = 0;
            const scrollTracked = {25: false, 50: false, 75: false, 100: false};
            
            const trackScroll = function() {
                const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                if (scrollHeight <= 0) return;
                
                const scrolled = (window.scrollY / scrollHeight) * 100;
                
                if (scrolled > maxScroll) {
                    maxScroll = scrolled;
                }
                
                // Track milestone scrolls
                const milestones = [25, 50, 75, 100];
                for (let i = 0; i < milestones.length; i++) {
                    const milestone = milestones[i];
                    if (scrolled >= milestone && !scrollTracked[milestone]) {
                        Analytics.trackEvent('scroll_depth', {
                            depth: milestone
                        });
                        scrollTracked[milestone] = true;
                    }
                }
            };
            
            if (window.addEventListener) {
                window.addEventListener('scroll', trackScroll, {passive: true});
                
                // Track final scroll depth on page exit
                window.addEventListener('beforeunload', function() {
                    if (maxScroll > 0) {
                        Analytics.trackEvent('page_exit', {
                            max_scroll_depth: Math.floor(maxScroll),
                            time_on_page: Analytics.getTimeOnPage()
                        });
                    }
                });
            }
        }
    };

    /**
     * Track AJAX/Fetch API calls
     * Intercepts fetch and XMLHttpRequest to track slow backend API calls
     */
    function initAjaxTracking() {
        if (!Analytics.isEnabled()) {
            return;
        }
        
        const slowThreshold = 1000; // 1 second default
        const performanceThreshold = 500; // Track all API calls > 500ms
        
        // Intercept fetch API
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const startTime = performance.now();
            const url = args[0];
            const options = args[1] || {};
            const method = options.method || 'GET';
            
            return originalFetch.apply(this, args)
                .then(response => {
                    const endTime = performance.now();
                    const responseTime = endTime - startTime;
                    
                    // Track slow API calls
                    if (responseTime >= slowThreshold) {
                        Analytics.trackEvent('api_slow', {
                            url: url,
                            method: method,
                            response_time: Math.round(responseTime),
                            status: response.status,
                            status_text: response.statusText
                        });
                    }
                    
                    // Track API performance (sample rate: 1 in 10, or all slow ones)
                    if (responseTime >= performanceThreshold || Math.random() < 0.1) {
                        Analytics.trackEvent('api_performance', {
                            url: url,
                            method: method,
                            response_time: Math.round(responseTime),
                            status: response.status
                        });
                    }
                    
                    return response;
                })
                .catch(error => {
                    const endTime = performance.now();
                    const responseTime = endTime - startTime;
                    
                    // Track failed/slow API calls
                    Analytics.trackEvent('api_error', {
                        url: url,
                        method: method,
                        response_time: Math.round(responseTime),
                        error: error.message || 'Network error'
                    });
                    
                    throw error;
                });
        };
        
        // Intercept XMLHttpRequest
        const originalXHROpen = XMLHttpRequest.prototype.open;
        const originalXHRSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, ...rest) {
            this._analyticsMethod = method;
            this._analyticsUrl = url;
            this._analyticsStartTime = performance.now();
            return originalXHROpen.apply(this, [method, url, ...rest]);
        };
        
        XMLHttpRequest.prototype.send = function(...args) {
            const xhr = this;
            const method = xhr._analyticsMethod;
            const url = xhr._analyticsUrl;
            const startTime = xhr._analyticsStartTime;
            
            xhr.addEventListener('loadend', function() {
                const endTime = performance.now();
                const responseTime = endTime - startTime;
                
                // Only track API calls (not static assets)
                if (url && (url.indexOf('/api/') !== -1 || url.indexOf('/ajax/') !== -1)) {
                    // Track slow API calls
                    if (responseTime >= slowThreshold) {
                        Analytics.trackEvent('api_slow', {
                            url: url,
                            method: method,
                            response_time: Math.round(responseTime),
                            status: xhr.status,
                            status_text: xhr.statusText
                        });
                    }
                    
                    // Track API performance (sample rate: 1 in 10, or all slow ones)
                    if (responseTime >= performanceThreshold || Math.random() < 0.1) {
                        Analytics.trackEvent('api_performance', {
                            url: url,
                            method: method,
                            response_time: Math.round(responseTime),
                            status: xhr.status
                        });
                    }
                }
            });
            
            xhr.addEventListener('error', function() {
                const endTime = performance.now();
                const responseTime = endTime - startTime;
                
                if (url && (url.indexOf('/api/') !== -1 || url.indexOf('/ajax/') !== -1)) {
                    Analytics.trackEvent('api_error', {
                        url: url,
                        method: method,
                        response_time: Math.round(responseTime),
                        error: 'Network error'
                    });
                }
            });
            
            return originalXHRSend.apply(this, args);
        };
        
        // Intercept axios if it's available
        if (window.axios) {
            // Axios uses interceptors
            axios.interceptors.request.use(function(config) {
                config._analyticsStartTime = performance.now();
                return config;
            });
            
            axios.interceptors.response.use(
                function(response) {
                    const endTime = performance.now();
                    const startTime = response.config._analyticsStartTime;
                    const responseTime = endTime - startTime;
                    const url = response.config.url;
                    const method = response.config.method.toUpperCase();
                    
                    // Only track API calls
                    if (url && (url.indexOf('/api/') !== -1 || url.indexOf('/ajax/') !== -1)) {
                        // Track slow API calls
                        if (responseTime >= slowThreshold) {
                            Analytics.trackEvent('api_slow', {
                                url: url,
                                method: method,
                                response_time: Math.round(responseTime),
                                status: response.status,
                                status_text: response.statusText
                            });
                        }
                        
                        // Track API performance (sample rate: 1 in 10, or all slow ones)
                        if (responseTime >= performanceThreshold || Math.random() < 0.1) {
                            Analytics.trackEvent('api_performance', {
                                url: url,
                                method: method,
                                response_time: Math.round(responseTime),
                                status: response.status
                            });
                        }
                    }
                    
                    return response;
                },
                function(error) {
                    const endTime = performance.now();
                    const startTime = error.config && error.config._analyticsStartTime ? error.config._analyticsStartTime : performance.now();
                    const responseTime = endTime - startTime;
                    const url = error.config ? error.config.url : '';
                    const method = error.config ? error.config.method.toUpperCase() : 'GET';
                    
                    if (url && (url.indexOf('/api/') !== -1 || url.indexOf('/ajax/') !== -1)) {
                        Analytics.trackEvent('api_error', {
                            url: url,
                            method: method,
                            response_time: Math.round(responseTime),
                            error: error.message || 'Request failed',
                            status: error.response ? error.response.status : null
                        });
                    }
                    
                    return Promise.reject(error);
                }
            );
        }
    }
    
    // Initialize AJAX tracking when Analytics is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAjaxTracking);
    } else {
        // DOM already loaded, initialize immediately
        initAjaxTracking();
    }
    
    // Export for use in other scripts
    window.Analytics = Analytics;

})(window, document);

