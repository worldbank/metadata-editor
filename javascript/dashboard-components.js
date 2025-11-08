(function (window) {
    'use strict';

    var VueRef = window.Vue;
    if (!VueRef) {
        console.error('Vue is not available. Please load Vue before dashboard-components.js');
        return;
    }

    var i18n = window.DashboardI18n || {};
    var LABEL_PAGE_VIEWS = i18n.PAGE_VIEWS || 'Page Views';
    var LABEL_LOAD_TIME = i18n.LOAD_TIME || 'Load Time';
    var LABEL_SERVER_TIME = i18n.SERVER_TIME || 'Server Time';
    var LABEL_TIME_MS = i18n.TIME_MS || 'Time (ms)';
    var LABEL_DATE = i18n.DATE || 'Date';
    var LABEL_ANONYMOUS = i18n.ANONYMOUS || 'Anonymous';
    var LABEL_USER = i18n.USER || 'User';
    var LABEL_API_KEY = i18n.API_KEY || 'API Key';
    var LABEL_JUST_NOW = i18n.JUST_NOW || 'Just now';
    var LABEL_MINUTES_AGO = i18n.MINUTES_AGO || 'minutes ago';
    var LABEL_HOURS_AGO = i18n.HOURS_AGO || 'hours ago';

    var formattingMixin = {
        methods: {
            formatNumber: function (value) {
                var number = Number(value || 0);
                if (Number.isNaN(number)) {
                    return value;
                }
                return number.toLocaleString();
            },
            formatSeconds: function (seconds) {
                if (seconds === null || seconds === undefined) {
                    return '—';
                }
                var value = Number(seconds);
                if (Number.isNaN(value)) {
                    return seconds;
                }
                if (Math.abs(value) < 1) {
                    return Math.round(value * 1000) + ' ms';
                }
                return value.toFixed(2) + ' s';
            },
            formatPercent: function (value, decimals) {
                if (decimals === void 0) { decimals = 1; }
                var number = Number(value || 0);
                return (number * 100).toFixed(decimals) + '%';
            },
            formatIsoDate: function (value) {
                if (!value) {
                    return '';
                }
                try {
                    var date = new Date(value);
                    if (isNaN(date.getTime())) {
                        return value;
                    }
                    return date.toLocaleString();
                } catch (e) {
                    return value;
                }
            }
        }
    };

    var DashboardOverview = {
        template: '#dashboard-overview-template',
        props: {
            stats: {
                type: Object,
                required: true
            }
        }
    };

    var AnalyticsSection = {
        template: '#analytics-section-template',
        mixins: [formattingMixin],
        props: {
            analytics: {
                type: Object,
                required: true
            },
            topUsers: {
                type: Array,
                default: function () { return []; }
            },
            topPages: {
                type: Array,
                default: function () { return []; }
            }
        },
        data: function () {
            return {
                trafficChart: null,
                hourlyChart: null
            };
        },
        computed: {
            hasData: function () {
                return Array.isArray(this.analytics && this.analytics.traffic_chart) && this.analytics.traffic_chart.length > 0;
            },
            slowPages: function () {
                var performance = this.analytics && this.analytics.performance;
                return performance && Array.isArray(performance.avg_load_times) ? performance.avg_load_times : [];
            }
        },
        watch: {
            analytics: {
                handler: function () {
                    this.refreshCharts();
                },
                deep: true,
                immediate: true
            }
        },
        methods: {
            refreshCharts: function () {
                var _this = this;
                this.$nextTick(function () {
                    _this.updateTrafficChart();
                    _this.updateHourlyChart();
                });
            },
            destroyCharts: function () {
                if (this.trafficChart) {
                    this.trafficChart.destroy();
                    this.trafficChart = null;
                }
                if (this.hourlyChart) {
                    this.hourlyChart.destroy();
                    this.hourlyChart = null;
                }
            },
            updateTrafficChart: function () {
                var canvas = this.$refs.trafficChartCanvas;
                var ChartLib = window.Chart;
                if (!canvas || !ChartLib) {
                    return;
                }
                if (this.trafficChart) {
                    this.trafficChart.destroy();
                    this.trafficChart = null;
                }
                var chartData = Array.isArray(this.analytics && this.analytics.traffic_chart) ? this.analytics.traffic_chart : [];
                var today = new Date();
                var labels = [];
                var values = [];
                for (var i = 29; i >= 0; i--) {
                    var date = new Date(today);
                    date.setDate(date.getDate() - i);
                    var isoDate = date.toISOString().split('T')[0];
                    var found = chartData.find(function (item) { return item.date === isoDate; });
                    labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                    values.push(found ? parseInt(found.views, 10) : 0);
                }
                var ctx = canvas.getContext('2d');
                this.trafficChart = new ChartLib(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: LABEL_PAGE_VIEWS,
                            data: values,
                            borderColor: '#1976D2',
                            backgroundColor: 'rgba(25, 118, 210, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: { size: 14 },
                                bodyFont: { size: 13 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { maxRotation: 45, minRotation: 45 }
                            }
                        }
                    }
                });
            },
            updateHourlyChart: function () {
                var canvas = this.$refs.hourlyChartCanvas;
                var ChartLib = window.Chart;
                if (!canvas || !ChartLib) {
                    return;
                }
                if (this.hourlyChart) {
                    this.hourlyChart.destroy();
                    this.hourlyChart = null;
                }
                var chartData = Array.isArray(this.analytics && this.analytics.hourly_traffic) ? this.analytics.hourly_traffic : [];
                var labels = [];
                var values = [];
                for (var i = 0; i < 24; i++) {
                    var found = chartData.find(function (item) { return parseInt(item.hour, 10) === i; });
                    if (i === 0) {
                        labels.push('12 AM');
                    } else if (i < 12) {
                        labels.push(i + ' AM');
                    } else if (i === 12) {
                        labels.push('12 PM');
                    } else {
                        labels.push((i - 12) + ' PM');
                    }
                    values.push(found ? parseInt(found.views, 10) : 0);
                }
                var ctx = canvas.getContext('2d');
                this.hourlyChart = new ChartLib(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: LABEL_PAGE_VIEWS,
                            data: values,
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76, 175, 80, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 2,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 8,
                                titleFont: { size: 12 },
                                bodyFont: { size: 11 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 12 }
                            }
                        }
                    }
                });
            },
            formatTime: function (ms) {
                if (!ms || ms === 0) return '0ms';
                if (ms < 1000) return Math.round(ms) + 'ms';
                var seconds = (ms / 1000).toFixed(1);
                return seconds + 's';
            },
            getPerformanceTextColor: function (ms) {
                if (!ms || ms === 0) return '';
                if (ms < 1000) return 'success';
                if (ms < 3000) return 'warning';
                return 'error';
            }
        },
        beforeDestroy: function () {
            this.destroyCharts();
        }
    };

    var ApiLogsSection = {
        template: '#api-logs-section-template',
        mixins: [formattingMixin],
        props: {
            apiLogs: {
                type: Object,
                required: true
            }
        },
        computed: {
            hasData: function () {
                return !!(this.apiLogs && this.apiLogs.has_data);
            },
            totals: function () {
                return this.apiLogs && this.apiLogs.totals ? this.apiLogs.totals : {
                    total_requests: 0,
                    success_count: 0,
                    error_count: 0,
                    avg_response_time: null,
                    error_rate: 0
                };
            },
            topEndpoints: function () {
                return this.apiLogs && Array.isArray(this.apiLogs.top_endpoints) ? this.apiLogs.top_endpoints : [];
            },
            topIps: function () {
                return this.apiLogs && Array.isArray(this.apiLogs.top_ips) ? this.apiLogs.top_ips : [];
            },
            topUsers: function () {
                return this.apiLogs && Array.isArray(this.apiLogs.top_users) ? this.apiLogs.top_users : [];
            }
        },
        methods: {
            getApiUserDisplay: function (user) {
                if (!user) {
                    return LABEL_ANONYMOUS;
                }
                if (user.display_name) {
                    return user.display_name;
                }
                if (user.username) {
                    return user.username;
                }
                if (user.user_id && user.user_id !== 0) {
                    return LABEL_USER + ' #' + user.user_id;
                }
                if (user.api_key) {
                    return LABEL_API_KEY + ' ' + user.api_key;
                }
                return LABEL_ANONYMOUS;
            }
        }
    };

    var ActivitySection = {
        template: '#activity-section-template',
        mixins: [formattingMixin],
        props: {
            activity: {
                type: Array,
                default: function () { return []; }
            },
            users: {
                type: Object,
                required: true
            }
        },
        computed: {
            inactiveUsersCount: function () {
                var statuses = Array.isArray(this.users && this.users.by_status) ? this.users.by_status : [];
                var inactive = statuses.find(function (status) { return status.status === 'inactive'; });
                return inactive ? inactive.count : 0;
            }
        },
        methods: {
            formatTimeUnix: function (timestamp) {
                if (!timestamp) {
                    return '';
                }
                var date = new Date(parseInt(timestamp, 10) * 1000);
                var now = new Date();
                var diff = now - date;
                if (diff < 60000) {
                    return LABEL_JUST_NOW;
                } else if (diff < 3600000) {
                    var mins = Math.floor(diff / 60000);
                    return mins + ' ' + LABEL_MINUTES_AGO;
                } else if (diff < 86400000) {
                    var hours = Math.floor(diff / 3600000);
                    return hours + ' ' + LABEL_HOURS_AGO;
                }
                return date.toLocaleDateString();
            }
        }
    };

    var SystemStatusSection = {
        template: '#system-status-section-template',
        mixins: [formattingMixin],
        props: {
            system: {
                type: Object,
                required: true
            },
            fastapiStatus: {
                type: Object,
                required: true
            },
            fastapiJobs: {
                type: Array,
                default: function () { return []; }
            },
            hasFastApiJobs: {
                type: Boolean,
                default: false
            },
            diskSpace: {
                type: Object,
                required: true
            },
            queueSize: {
                type: [Number, String],
                default: null
            }
        },
        computed: {
            fastapiJobsList: function () {
                return Array.isArray(this.fastapiJobs) ? this.fastapiJobs : [];
            },
            diskSpaceColor: function () {
                if (!this.diskSpace || !this.diskSpace.available) {
                    return 'grey';
                }
                switch (this.diskSpace.status) {
                    case 'critical':
                        return 'error';
                    case 'warning':
                        return 'warning';
                    default:
                        return 'success';
                }
            }
        }
    };

    window.DashboardFormattingMixin = formattingMixin;

    VueRef.component('dashboard-overview', DashboardOverview);
    VueRef.component('analytics-section', AnalyticsSection);
    VueRef.component('api-logs-section', ApiLogsSection);
    VueRef.component('activity-section', ActivitySection);
    VueRef.component('system-status-section', SystemStatusSection);

})(window);
