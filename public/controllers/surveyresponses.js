Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");
Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});

new Vue({

    el: '#survey-responses-report',

    data: {
        surveyQuestions: [],
        surveyResponses: [],
        question: '',
        counties: [],
        subcounties: [],
        facilities: [],
        rounds: [],
        loading: false,
        pagination: {
            total: 0, 
            per_page: 2,
            from: 1, 
            to: 0,
            current_page: 1
        },
        offset: 4,
        formErrors:{},
        formErrorsUpdate:{},
        total: '',
        round: '3',
        facility: '',
        sub_county: '',
        county: '',
        role: '',
        agreement: ["Strongly disagree", "Disagree", "Neither agree nor disagree", "Agree", "Strongly agree"],
        yesNo: ["No", "Yes"],
    },

    computed: {
        isActived: function () {
            return this.pagination.current_page;
        },
        pagesNumber: function () {
            if (!this.pagination.to) {
                return [];
            }
            var from = this.pagination.current_page - this.offset;
            if (from < 1) {
                from = 1;
            }
            var to = from + (this.offset * 2);
            if (to >= this.pagination.last_page) {
                to = this.pagination.last_page;
            }
            var pagesArray = [];
            while (from <= to) {
                pagesArray.push(from);
                from++;
            }
            return pagesArray;
        }
    },

    mounted : function(){
        this.getRole();
        this.loadRounds();

    },

    methods : {

        getSurveyResponses: function(page){
            var input = {'page': page, 'county': this.county, 'subcounty': this.sub_county, 'facility': this.facility, 'round': this.round, 'question': this.question};
            this.loading = true;

            this.$http.post('/get-survey-responses', input).then((response) => {
                if(response.data.data)
                {
                    this.surveyResponses = response.data.data.data;
                    this.total = response.data.total_users;
                    this.questions = response.data.questions;
                    this.pagination = response.data.pagination;
                }else{
                    this.surveyResponses = [];
                }
                this.loading = false;
            }, (response) => {
                this.formErrors = response.data;
                this.surveyResponses = [];
                this.loading = false;
            });
        },
        
        getSurveyQuestions: function(){
            if (this.round) {
                let input = {'round_id': this.round};
                this.loading = true;

                this.$http.post('/get-survey-questions', input).then((response) => {
                    if(response.data.data)
                    {
                        this.surveyQuestions = response.data.data;
                    }else{
                        this.surveyQuestions = [];
                        toastr.error("No questions found for the round!", 'Failure Alert', {timeOut: 7000});
                    }
                    this.loading = false;
                }, (response) => {
                    this.formErrors = response.data;
                    this.surveyQuestions = [];
                    this.loading = false;
                });
            }else{
                toastr.error(response.data.response, 'Failure Alert', {timeOut: 7000});
            }
        },
        
        getRole: function(page){
            this.$http.get('/userrole').then((response) => {
                if(response.data){
                    this.role = response.data.role_id;
                    this.loadCounties();
                    if (this.role == 4) { //County Role
                        this.county = response.data.tier;
                        this.loadSubcounties();
                    }
                    if (this.role == 7) {// Subcounty Role
                        this.sub_county = response.data.tier;
                        this.loadFacilities();
                    }
                }
            })
        },

        changePage: function (page) {
            this.pagination.current_page = page;
            this.getSurveyResponses(page);
        },

        //Populate counties from FacilityController
        loadCounties: function() {
            var url = '/cnts';
            if(this.role == 3) url = '/partnercounties'
            this.$http.get(url).then((response) => {
                this.counties = response.data;
                this.jimbo = response.data;
            }, (response) => {
            });
        },

        // Populate subcounties from FacilityController
        loadSubcounties: function() {
            this.sub_county = "";
            this.facility = "";
            this.$http.get('/subs/'+ this.county).then((response) => { 
                this.subcounties = response.data;
            }, (response) => {
            });
        }, 

        // Populate facilities from FacilityController
        loadFacilities: function() {
            this.facility = "";
            this.$http.get('/fclts/' + this.sub_county).then((response) => { 
                this.facilities = response.data;
            }, (response) => {
            });
        },
        loadRounds: function() {
            this.$http.get('/rnds').then((response) => {
                this.rounds = response.data;
            }, (response) => {
            });
        },

        getRoundName(key) {
    	    for(i = 0; i < this.rounds.length; i++){
    	        if(this.rounds[i].id == key) return this.rounds[i].value;
    	    }
            return '';
        },
    }
});
