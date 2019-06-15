<template>
    <div>
        <h2>Price ranges for {{message}}</h2>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Price</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>1,001</td>
                    <td>Lorem</td>
                    <td>ipsum</td>
                    <td>ipsum</td>
                </tr>
                <tr>
                    <td>1,015</td>
                    <td>sodales</td>
                    <td>ligula</td>
                    <td>ipsum</td>
                </tr>
                </tbody>
            </table>
        </div>
        <input id="monthPicker" type="text"/>
    </div>
</template>

<script>
    const axios = require('axios');
    module.exports ={
        name: "price-list-by-month",
        data: function() {
            return {
                message: 'Some Message'
            };
        },
        methods: {
            getCurrentPrices() {
                axios.get('/prices.php')
                    .then(function (response) {
                        // handle success
                        console.log(response);
                    })
                    .catch(function (error) {
                        // handle error
                        console.log(error);
                    })

            }
        },
        mounted() {
            this.getCurrentPrices();
            $('#monthPicker').MonthPicker({Button: false});
        }
    }
</script>

<style scoped>

</style>