Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");

new Vue({

  el: '#manage-report',

  data: {
    tallies: [],
    percentiles: [],
    uns: [],
    talliesChart: null,
    percentilesChart: null,
    unsChart: null,
    from: '',
    to: '',
    rounds: [],
    loading: false,
    error: false,
    query: ''
  },

  computed: {
    },

  ready : function(){
        this.loadRounds();
        this.getVueReports();
        this.getTallies();
        this.getPercentiles();
        this.getUnperfs();
  		//this.getTallies(this.from, this.to);
        //this.getPercentiles(this.from, this.to);
        //this.getUns(this.from, this.to);
  },

  methods : {
        getVueReports: function(page){
          this.$http.get('/vuereports').then((response) => {
            console.log(response);
            this.$set('tallies', response.data.summaries);
            this.$set('percentiles', response.data.percentiles);
            this.$set('uns', response.data.unsperf);
          });
        },

        getTallies: function(){
            Highcharts.chart('talliesContainer', {
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Enrollment, Response and Satisfaction'
                },
                subtitle: {
                    text: 'Rounds 13 - 16'
                },
                xAxis: {
                    categories: [
                        'Round 13',
                        'Round 14',
                        'Round 15',
                        'Round 16'
                    ],
                    crosshair: true
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Counts'
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0
                    }
                },
                series: [{
                    name: 'Enrollment',
                    data: [7333, 7319, 9541, 19600]

                }, {
                    name: 'Response',
                    data: [4283, 4152, 7534, 0]

                }, {
                    name: 'Satisfactory',
                    data: [3609, 3062, 6069, 0]

                }, {
                    name: 'Unsatisfactory',
                    data: [674, 1090, 1465, 0]

                }]
            });
        },

        getPercentiles: function(){
            Highcharts.chart('persContainer', {
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Enrollment, Response and Satisfaction'
                },
                subtitle: {
                    text: 'Rounds 13 - 16'
                },
                xAxis: {
                    categories: [
                        'Round 13',
                        'Round 14',
                        'Round 15',
                        'Round 16'
                    ],
                    crosshair: true
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Counts'
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0
                    }
                },
                series: [{
                    name: 'Enrollment',
                    data: [7333, 7319, 9541, 19600]

                }, {
                    name: 'Response',
                    data: [4283, 4152, 7534, 0]

                }, {
                    name: 'Satisfactory',
                    data: [3609, 3062, 6069, 0]

                }, {
                    name: 'Unsatisfactory',
                    data: [674, 1090, 1465, 0]

                }]
            });
        },

        getUnperfs: function(){
            Highcharts.chart('unsperfContainer', {
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Enrollment, Response and Satisfaction'
                },
                subtitle: {
                    text: 'Rounds 13 - 16'
                },
                xAxis: {
                    categories: [
                        'Round 13',
                        'Round 14',
                        'Round 15',
                        'Round 16'
                    ],
                    crosshair: true
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Counts'
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0
                    }
                },
                series: [{
                    name: 'Enrollment',
                    data: [7333, 7319, 9541, 19600]

                }, {
                    name: 'Response',
                    data: [4283, 4152, 7534, 0]

                }, {
                    name: 'Satisfactory',
                    data: [3609, 3062, 6069, 0]

                }, {
                    name: 'Unsatisfactory',
                    data: [674, 1090, 1465, 0]

                }]
            });
        },

      loadRounds: function() {
        this.$http.get('/rnds').then((response) => {
            this.rounds = response.data;

        }, (response) => {
            console.log(response);
        });
      },

      getData: function() {
        this.$http.get('/rdata').then((response) => {
            this.tallies = response.data;
            this.percentiles = response.data;
            this.uns = response.data;

        }, (response) => {
            console.log(response);
        });
      },
  }

});