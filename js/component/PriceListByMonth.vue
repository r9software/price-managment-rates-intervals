<template>
    <div style="min-width: 1024px">
        <edit-price-modal :price="currentPriceEdit" :month="month"></edit-price-modal>
        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-8">
                <label for="monthPicker">Select Month to choose</label>
                <input id="monthPicker" v-model="date" type="text" style="margin-left:30px"/>
            </div>
            <div class="col-md-2"></div>
        </div>
        <div class="row content-body" v-if="prices.length>0">
            <div class="col-md-2">
                <loading :active.sync="isLoading"
                         :can-cancel="true"
                         :is-full-page="true"></loading>
            </div>
            <div class="col-md-8">
                <h4>Price ranges for {{month}}</h4>
                <div class="table-responsive table-size">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Price</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="price in prices">
                            <td>{{formatDate(price.date_start)}}</td>
                            <td>{{formatDate(price.date_end)}}</td>
                            <td>{{price.price}}</td>
                            <td>
                                <button type="button" class="btn" data-toggle="modal" data-target="#editModal">
                                    <i class="far fa-edit" @click="editRange(price)"></i></button>
                                <i class="far fa-trash-alt" @click="deleteRange(price.id)"></i>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-2"></div>
        </div>
        <div class="row content-body" v-else>
            <div class="col-md-2"></div>
            <div class="col-md-8">
                <h4>No records for {{month}}, add one</h4></div>
            <div class="col-md-2"></div>
        </div>

        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Start day</label>
                    <datepicker v-model="startDay" id="startDay" :value="date" :format="customFormatter"
                                aria-describedby="dayHelp" placeholder="Start day of period"></datepicker>
                    <small id="dayHelp" class="form-text text-muted">This will be the first day of the period for this
                        price
                    </small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>End day</label>
                    <datepicker v-model="endDay" id="endDay" :value="date" :format="customFormatter"
                                aria-describedby="endDayHelp" placeholder="End day of period"></datepicker>
                    <small id="endDayHelp" class="form-text text-muted">This will be the end day of the period for
                        this price
                    </small>
                </div>
            </div>
            <div class="col-md-2"></div>
        </div>

        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" v-model="price" class="form-control" id="price" aria-describedby="priceHelp"
                           placeholder="Price of period">
                    <small id="priceHelp" class="form-text text-muted">This will be the price for that period,
                        <span class="red-text">Remember: </span> new interval price have higher priority over existing
                        ones.
                    </small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary" @click="saveDate">Submit</button>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-warning" @click="clearDatabase">Clear Database</button>
            </div>
        </div>
    </div>
</template>

<script>
    const axios = require('axios');
    const Datepicker = require('vuejs-datepicker');
    const EditPriceModal = require('./EditPriceModal.vue');
    const Loading = require('vue-loading-overlay');
    const Swal = require('sweetalert2');
    module.exports = {
        name: "price-list-by-month",
        data: function () {
            return {
                isLoading: false,
                date: '',
                startDay: "",
                endDay: "",
                price: 0,
                currentPriceEdit: {},
                prices: [],
            };
        },
        /**
         * display selected month to the user
         */
        computed: {
            'month': function () {
                return moment(this.date, 'YYYY-MM').format('MMMM YY');
            }
        },
        /**
         *
         * get current prices for month when date changes
         **/
        watch: {
            'date': function () {
                if (typeof this.date !== 'undefined') {
                    this.getCurrentPrices();
                }
            },
            'endDay': function () {
                if (this.startDay !== "" && this.endDay !== "")
                    if (this.endDay < this.startDay) {
                        Swal.fire(
                            'Is the end day wrong?',
                            'The end day should be bigger than start date',
                            'question'
                        );
                        this.endDay = "";
                    }
            },
            'startDay': function () {
                if (this.startDay !== "" && this.endDay !== "")
                    if (this.startDay > this.endDay) {
                        Swal.fire(
                            'Is the start day wrong?',
                            'The start day should be less than the end date',
                            'question'
                        );
                        this.startDay = "";
                    }
            },
        },
        components: {
            Loading,
            EditPriceModal,
            Datepicker
        },
        methods: {
            /**
             * return formatted date
             */
            formatDate(date) {
                return moment(date, 'YYYY-MM-DD').format('DD MMMM YYYY');
            },
            /**
             * This method clears the database
             */
            clearDatabase(){

                this.isLoading = true;
                let self=this;
                axios.get('/price-managment-rates-intervals/prices.php?method=clear')
                    .then(function (response) {
                        // handle success
                        self.isLoading = false;
                        self.startDay = "";
                        self.endDay = "";
                        self.price = "";
                        self.getCurrentPrices();
                        Swal.fire('Success',
                            'Database cleared',
                            'success')
                    }).catch(function (error) {
                    // handle error
                    Swal.fire('Error',
                        'Database not cleared',
                        'error')
                    console.log(error);
                })
            },
            /**
             * change current range for modal
             */
            editRange(price) {
                this.currentPriceEdit = price;
            },
            /**
             * Saves the range
             */
            saveDate() {
                if (this.startDay == null || this.endDay == null) {
                    Swal.fire(
                        'Are the dates wrong?',
                        'You need to select the dates first',
                        'question'
                    )
                } else {
                    let self = this;
                    this.isLoading = true;
                    let startDate = moment(this.startDay, 'MMMM Do YYYY, h:mm:ss a').format('YYYY-MM-DD');
                    let endDate = moment(this.endDay, 'MMMM Do YYYY, h:mm:ss a').format('YYYY-MM-DD');
                    axios.get('/price-managment-rates-intervals/prices.php?method=add&date_start=' + startDate + '&date_end=' + endDate + "&price=" + this.price)
                        .then(function (response) {
                            // handle success
                            self.isLoading = false;
                            self.startDay = "";
                            self.endDay = "";
                            self.price = "";
                            self.getCurrentPrices();
                            Swal.fire('Success',
                                'Range Saved',
                                'success')
                        }).catch(function (error) {
                        // handle error
                        Swal.fire('Error',
                            'Range not saved',
                            'error')
                        console.log(error);
                    })
                }

            },
            /**
             * Custom formatter for adding a new range
             */
            customFormatter(date) {
                return moment(date).format('DD-MM-YYYY');
            },
            /**
             * Calls the delete endpoint
             * since API server is limited it has to be with get method,
             * but obviously with a framework or a better server we could change that
             */
            deleteRange(id) {
                this.isLoading = true;
                let self = this;
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.value) {
                        axios.get('/price-managment-rates-intervals/prices.php?id=' + id + '&method=delete').then(function (response) {
                            // handle success
                            self.isLoading = false;
                            self.getCurrentPrices();
                            Swal.fire('Success',
                                'Range deleted',
                                'success')
                        }).catch(function (error) {
                            // handle error
                            Swal.fire('Error',
                                'Range not found',
                                'error')
                            console.log(error);
                        })
                    }
                });
            },
            /**
             * Returns the list of ranges for the month year selected
             */
            getCurrentPrices() {
                this.isLoading = true;
                let self = this;
                this.date = moment(this.date).format('YYYY-MM');
                axios.get('/price-managment-rates-intervals/prices.php?date=' + this.date)
                    .then(function (response) {
                        // handle success
                        self.prices.splice(0, self.prices.length);
                        self.isLoading = false;
                        response.data.forEach((item) => {
                            self.prices.push(item);
                        });
                    })
                    .catch(function (error) {
                        // handle error
                        console.log(error);
                    })

            }
        },
        /**
         * Initialize date obj, month picker library
         * reloads prices when modal is closed
         */
        mounted() {
            this.date = moment().format('YYYY-MM');
            let self = this;
            $("#monthPicker").MonthPicker({
                Button: false,
                SelectedMonth: self.date,
                OnAfterChooseMonth: function (selectedDate) {
                    self.date = selectedDate;
                }
            });
            $('#editModal').on('hidden.bs.modal', function (e) {
                self.getCurrentPrices();
            })
        }
    }
</script>

<style scoped>
    .red-text {
        color: red;
    }

    .table-size {
        width: 600px;
    }

    .content-body {
        margin-top: 40px;
    }
</style>