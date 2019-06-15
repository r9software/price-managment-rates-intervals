export default  {
    data() {
        return{
            message: 'Hello Vue!'
        }
    },
    methods:{
        getCurrentPrices(){
            axios.get('/user?ID=12345')
                .then(function (response) {
                    // handle success
                    console.log(response);
                })
                .catch(function (error) {
                    // handle error
                    console.log(error);
                })
                .finally(function () {
                    // always executed
                });

        }
    },
    mounted() {
        this.getCurrentPrices();
        $('#monthPicker').MonthPicker({Button: false});
    }
}