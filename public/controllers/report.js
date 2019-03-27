Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");
Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});
new Vue({

    el: '#manage-report',

    data: {
        tallies: [],
        percentiles: [],
        uns: [],
        talliesChart: [],
        percentilesChart: [],
        unsChart: [],
        from: '',
        to: '',
        rounds: [],
        loading: false,
        error: false,
        query: ''
    },

    computed: 
    {
    },

    mounted : function()
    {
        this.loadRounds();
        this.getVueReports();
    },

    methods : {
        getVueReports: function(page){
          this.$http.get('/vuereports').then((response) => {
            this.tallies = response.data.summaries;
            this.percentiles = response.data.percentiles;
            this.uns = response.data.unsperf;
            this.talliesChart = response.data.summariesChart;
            this.percentilesChart = response.data.percentilesChart;
            this.unsChart = response.data.unsPerfChart;
            this.getTallies(this.talliesChart);
            this.getPercentiles(this.percentilesChart);
            this.getUnperfs(this.unsChart);
          });
        },

        getTallies: function(content)
        {
            var xCategories = this.buildChart(content)[0];
            var seriesData = this.buildChart(content)[1];
        
            $('#talliesContainer').highcharts({
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Enrolment, Response and Performance',
                    x: -20 //center
                },
                subtitle: {
                    text: '',
                    x: -20
                },
                xAxis: {
                    categories: xCategories
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Count'
                    }
                },
                colors: ['#00acc1', '#bf360c', '#afb42b', '#45526E'],
                credits: {
                    enabled: false
                },
                series:seriesData
            });
        },

        getPercentiles: function(content)
        {
            var xCategories = this.buildChart(content)[0];
            var seriesData = this.buildChart(content)[1];
        
            $('#persContainer').highcharts({
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Response and Satisfaction Rates (%)',
                    x: -20 //center
                },
                subtitle: {
                    text: '',
                    x: -20
                },
                xAxis: {
                    categories: xCategories
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Percentage'
                    }
                },
                colors: ['#00acc1', '#afb42b'],
                credits: {
                    enabled: false
                },
                series:seriesData
            });
        },

        getUnperfs: function(content){
            var xCategories = this.buildChart(content)[0];
            var seriesData = this.buildChart(content)[1];
        
            $('#unsPerfContainer').highcharts({
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Reasons for Unsatisfactory Performance',
                    x: -20 //center
                },
                subtitle: {
                    text: '',
                    x: -20
                },
                xAxis: {
                    categories: xCategories
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Percentage'
                    }
                },
                colors: ['#2E2E2E', '#007E33', '#e91e63', '#6d4c41','#1a237e', '#ff6f00', '#bf360c', '#607d8b', '#45526E'],
                credits: {
                    enabled: false
                },
                series:seriesData
            });
        },

        loadRounds: function() 
        {
            this.$http.get('/rnds').then((response) => {
                this.rounds = response.data;
            }, (response) => {
                //console.log(response);
            });
        },

        getData: function() 
        {
            this.$http.get('/rdata').then((response) => {
                this.tallies = response.data;
                //console.log(this.tallies);
                this.percentiles = response.data;
                this.uns = response.data;
            }, (response) => {
                //console.log(response);
            });
        },

        buildChart: function(content)
        {
            var jsonRoundData = content;
                
            var seriesData = [];
            var xCategories = [];
            var i, cat;
            for(i = 0; i < jsonRoundData.length; i++)
            {
                cat = '' + jsonRoundData[i].round;
                if(xCategories.indexOf(cat) === -1)
                {
                   xCategories[xCategories.length] = cat;
                }
            }
            for(i = 0; i < jsonRoundData.length; i++)
            {
                if(seriesData)
                {
                    var currSeries = seriesData.filter(function(seriesObject)
                    {
                        return seriesObject.name == jsonRoundData[i].title;
                    });
                    if(currSeries.length === 0)
                    {
                        seriesData[seriesData.length] = currSeries = {name: jsonRoundData[i].title, data: []};
                    } 
                    else 
                    {
                        currSeries = currSeries[0];
                    }
                    var index = currSeries.data.length;
                    currSeries.data[index] = jsonRoundData[i].total;
                } 
                else 
                {
                   seriesData[0] = {name: jsonRoundData[i].title, data: [jsonRoundData[i].total]}
                }
            }
            return [xCategories, seriesData];
        }
    }
});