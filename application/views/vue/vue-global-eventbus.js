const EventBus = new Vue();

//Global confirm function
Vue.prototype.$confirm = function (message) {
    return new Promise((resolve, reject) => {
        EventBus.$emit('confirm', { message, resolve, reject });
    });
};

//Global alert function
Vue.prototype.$alert = function (message, options = {}) {
    return new Promise((resolve) => {
        EventBus.$emit('alert', { message, ...options, resolve });
    });
};

//Global error message extractor
Vue.prototype.$extractErrorMessage = function (error) {
    if (error.response?.data?.message) {
        return error.response.data.message;
    }
    if (error.response?.data) {
        return JSON.stringify(error.response.data);
    }
    return error.message || "An unknown error occurred";
};

