<template>
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-center" id="editModalLabel">Edit range for {{month}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Start day</label>
                                <datepicker v-model="startDay" id="startDay" :format="customFormatter"
                                            aria-describedby="dayHelp" placeholder="Start day of period"></datepicker>
                                <small id="dayHelp" class="form-text text-muted">This will be the first day of the
                                    period for this
                                    price
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>End day</label>
                                <datepicker v-model="endDay" id="endDay" :format="customFormatter"
                                            aria-describedby="endDayHelp" placeholder="End day of period"></datepicker>
                                <small id="endDayHelp" class="form-text text-muted">This will be the end day of the
                                    period for
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
                                <label for="price">Price</label>
                                <input type="number" v-model="priceDays" class="form-control" id="price"
                                       aria-describedby="priceHelp"
                                       placeholder="End day of period">
                                <small id="priceHelp" class="form-text text-muted">This will be the price for that
                                    period,
                                    <span class="red-text">Remember: </span> new interval price have higher priority
                                    over existing
                                    ones.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" @click="saveChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    const Datepicker = require('vuejs-datepicker');
    const axios = require('axios');
    module.exports = {
        props: ['price', 'month'],
        data: function () {
            return {
                id: 0,
                startDay: "",
                endDay: "",
                priceDays: 0,
            };
        },
        components: {
            Datepicker
        },
        name: "edit-price-modal",
        //watch price object to reset modal when a new row is clicked
        watch: {
            'price': function () {
                if (typeof this.price !== 'undefined' && this.price.id != this.id) {
                    this.id = this.price.id;
                    this.startDay=moment(this.price.date_start, 'YYYY-MM-DD').toDate();
                    this.endDay=moment(this.price.date_end, 'YYYY-MM-DD').toDate();
                    this.priceDays = this.price.price;
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
                        this.endDay="";
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
                        this.startDay="";
                    }
            },
        },
        methods:{

            /**
             * Custom formatter for adding a new range
             */
            customFormatter(date) {
                return moment(date).format('DD-MM-YYYY');
            },
            /***
             * Save changes depending on what was changed
             */
            saveChanges(){
                let dateStart = moment(this.startDay, 'MMMM Do YYYY, h:mm:ss a').format('YYYY-MM-DD');
                let dateEnd = moment(this.endDay, 'MMMM Do YYYY, h:mm:ss a').format('YYYY-MM-DD');
                let dateChanged=false;
                if(this.price.date_start!==dateStart || this.price.date_end!==dateEnd)
                    dateChanged=true;
                 axios.get('/price-managment-rates-intervals/prices.php?method=update&id='+this.id+'&date_changed='+dateChanged+'&date_start=' + dateStart + '&date_end=' + dateEnd + "&price=" + this.priceDays)
                    .then(function (response) {
                        // handle success
                        Swal.fire('Success',
                            'Range Updated correctly',
                            'success');
                        $('#editModal').modal('hide');
                    }).catch(function (error) {
                    // handle error
                    Swal.fire('Error',
                        'Range not saved',
                        'error');
                })
            }
        },
        mounted() {
        }
    }
</script>

<style scoped>

</style>