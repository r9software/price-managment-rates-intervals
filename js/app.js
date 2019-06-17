/**
 * Initialize the app and components
 */
var Vue = require('vue');
Vue.use(require('vue-resource'));
const PriceListByMonth = require('./component/PriceListByMonth.vue');
new Vue({
    el: '#app',
    components: {
        PriceListByMonth,
    },
});