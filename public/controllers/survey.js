Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");

Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});

new Vue({

    el: '#manage-survey',

    data: {
        surveys: [],
        questionTypes: [],
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
        newSurvey : {'question_type':'','question':''},
        fillSurvey : {'question_type':'','question':'','id':''},
        loading: false,
        error: false,
        query: ''
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
        this.getVueSurveys(this.pagination.current_page);
        this.getSurveyQuestionTypes();
    },

    methods : {

        getVueSurveys: function(page){
            this.$http.get('/vuesurveys?page='+page).then((response) => {
                if(response.data.error){
                    toastr.error(response.data.error, 'No survey questions found!', {timeOut: 10000});
                }else{
                    this.surveys = response.data.data.data;
                    this.pagination = response.data.pagination;
                }
            }, (response) => {
                toastr.error(response, 'No survey questions found!', {timeOut: 5000});
                });
        },

        getSurveyQuestionTypes: function(){
            this.$http.get('/survey-question-types').then((response) => {
                this.questionTypes = response.data;
            });
        },

        getQuestionTypeTag: function(id){
            let tag = '';
            let qt = this.questionTypes;
            for (let i = qt.length - 1; i >= 0; i--) {
                if(qt[i].id == id){
                    tag = qt[i].tag;
                }
            }
            return tag;
        },

        createSurvey: function(scope){
            this.$validator.validateAll(scope).then(() => {
                var input = this.newSurvey;
                this.$http.post('/vuesurveys',input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    this.newSurvey = {'question':'','question_type':''};
                    $("#create-survey").modal('hide');
                    toastr.success('Survey Created Successfully.', 'Success Alert', {timeOut: 5000});
                }, (response) => {
                    this.formErrors = response.data;
                });
            }).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
                return false;
            });
        },

        deleteSurvey: function(survey){
            this.$http.delete('/vuesurveys/'+survey.id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Survey Deleted Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        restoreSurvey: function(survey){
            this.$http.patch('/vuesurveys/'+survey.id+'/restore').then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Survey Restored Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        editSurvey: function(survey){
            this.fillSurvey.question_type = survey.question_type;
            this.fillSurvey.id = survey.id;
            this.fillSurvey.question = survey.question;
            $("#edit-survey").modal('show');
        },

        updateSurvey: function(id, scope){
            this.$validator.validateAll(scope).then(() => {
                let input = this.fillSurvey;
                this.$http.put('/vuesurveys/'+id, input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    this.fillSurvey = {'question_type':'','question':'','id':''};
                    $("#edit-survey").modal('hide');
                    toastr.success('Survey Updated Successfully.', 'Success Alert', {timeOut: 5000});
                }, (response) => {
                    this.formErrorsUpdate = response.data;
                });
            }).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
                return false;
            });
        },

        changePage: function (page) {
            this.pagination.current_page = page;
            this.getVueSurveys(page);
        },

        search: function() {
            // Clear the error message.
            this.error = '';
            // Empty the surveys array so we can fill it with the new surveys.
            this.surveys = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_survey?q=' + this.query).then((response) => {
                // If there was an error set the error message, if not fill the surveys array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                {
                    this.surveys = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.query = '';
            });
        }
    }
});