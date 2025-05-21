const EventBus = new Vue();

//Global confirm function
Vue.prototype.$confirm = function (message) {
    return new Promise((resolve, reject) => {
        EventBus.$emit('confirm', { message, resolve, reject });
    });
};

