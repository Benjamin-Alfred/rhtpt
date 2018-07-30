Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");
Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});
new Vue({

    el: '#manage-result',

    data: {
        results: [],
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
        analysisResult : {'round_id':'','field_id[]':'','response[]':'','comment[]':''},
        fillResult : {'round_id':'','field_id[]':'','response[]':'','comment[]':'','id':''},
        fillVerifiedResult: {},
        form: [],
        sets: [],
        rounds: [],
        roundsDone: [],
        frmData: {},
        viewFormData:{},
        evaluated_results:[],
        loading: false,
        error: false,
        query: '',
        other: '',
        dt: [],
        //variables used in the filters
        counties:[],
        subs: [],
        facilities:[],
        role: '',
        county:'',
        sub_county:'',
        facility:'',
        result_status:'',
        feedback_status: '',
        filters:'',
        toggle: {}
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
    	this.getVueResults(this.pagination.current_page);
        this.loadRounds();
        this.loadRoundsDone();
        this.getForm();
        this.getSets();
        this.loadCounties();
    },

    methods : {
        getVueResults: function(page){
            this.$http.get('/vueresults?page='+page).then((response) => {
                if(response.data.data)
                {
                    this.results = response.data.data.data;
                    this.pagination = response.data.pagination;
                    this.role = response.data.user_role;
                }
                else
                {
                    swal("No data found for PT results.", "", "info");
                }
            });
        },

        createResult: function(){
           let myForm = document.getElementById('analysis_results');
            let formData = new FormData(myForm);
      		this.$http.post('/vueresults', formData).then((response) => {
    		    this.changePage(this.pagination.current_page);
      			$("#create-result").modal('hide');
      			toastr.success('Result Saved Successfully.', 'Success Alert', {timeOut: 5000});
      		}, (response) => {
  			    this.formErrors = response.data;
          	});
      	},

        deleteResult: function(result){
            this.$http.delete('/vueresults/'+result.id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Result Deleted Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        restoreResult: function(result){
            this.$http.patch('/vueresults/'+result.id+'/restore').then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Result Restored Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        editResult: function(result){
            //    Fetch the result using the id
            let id = result.id;
            this.$http.get('/pt/'+id).then((response) => {
                // console.log(response.data);
                this.frmData = response.data;
            });
            $("#edit-result").modal('show');
        },

        viewResult: function(result){
            //    Fetch the result using the id
            let id = result.id;
            this.$http.get('/pt/'+id).then((response) => {
                this.viewFormData = response.data;
            });
            $("#view-result").modal('show');
        },

        updateResult: function(id, scope){
            // this.$validator.validateAll(scope).then(() => {
                // var input = this.fillResult;

                let myForm = document.getElementById('update_test_results');
                let input = new FormData(myForm);
                // console.log(...input);
                this.$http.post('/update_results/'+id,input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    // this.fillResult = {'round_id':'','field_id[]':'','response[]':'','comment[]':'','id':''};
                    $("#edit-result").modal('hide');
                    toastr.success('Result Updated Successfully.', 'Success Alert', {timeOut: 5000});
                }, (response) => {
                    this.formErrorsUpdate = response.data;
                });
            // }).catch(() => {
            //     toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
            //     return false;
            // });
        },

        verifyResult: function(){
            let myForm = document.getElementById('verify_test_results');
            let formData = new FormData(myForm);
            this.$http.post('/verify_results', formData).then((response) => {
                this.changePage(this.pagination.current_page);
                $("#view-result").modal('hide');
                toastr.success('Result Verified Successfully.', 'Success Alert', {timeOut: 5000});
            });
        }, 

        changePage: function (page) {
            this.pagination.current_page = page;
            if (this.filters ==1) {
                this.filter(page);
            }else{

                this.getVueResults(page);
            }
        },

        getForm: function(){
            this.$http.get('/form').then((response) => {
                this.form = response.data.sets;
            }, (response) => {
                // console.log(response.data.sets);
            });
        },

        getSets: function(){
            this.$http.get('/frmSets').then((response) => {
                this.sets = response.data.sets;
            }, (response) => {
                // console.log(response.data.sets);
            });
        },

        loadRounds: function() {
            this.$http.get('/rnds').then((response) => {
                this.rounds = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        loadRoundsDone: function() {
            this.$http.get('/rndsDone').then((response) => {
                this.roundsDone = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        // Verfiy the evaluated 
        showEvaluatedResults: function(result){
            //    Fetch the result using the id
            let id = result.id;
            this.$http.get('/show_evaluated_results/'+id).then((response) => {
                this.evaluated_results = response.data;
            });
            $("#view-evaluted-result").modal('show');
        },

        //User reviews and saves the comments on the evaluated results
        verifyEvaluatedResult: function(id){
            let myForm = document.getElementById('verify_evaluated_test_results');
            let formData = new FormData(myForm);
            // console.log(formData);
            this.$http.post('/verify_evaluated_results/'+id, formData).then((response) => {
                this.changePage(this.pagination.current_page);
                $("#view-evaluted-result").modal('hide');
                toastr.success('Result Verified Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },        

        //A shortcut on quick verification on the satisfactory results
        quickVerifyEvaluatedResult: function(id){
            // let id = result.id;
            this.$http.get('/verify_evaluated_results/'+id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Result Verified Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        //Incase of mis-evaluation or any human intervention, update the evaluated results
        show_update_evaluated_results: function(){

            $("#view-evaluted-result").modal('hide');
            $("#update-evaluated-result").modal('show');                
        },

        update_evaluated_results: function(id){
            let myForm = document.getElementById('update_evaluated_results');
            let formData = new FormData(myForm);
            // console.log(formData);
            this.$http.post('/update_evaluated_results/'+id, formData).then((response) => {
                this.changePage(this.pagination.current_page);
                $("#update-evaluated-result").modal('hide');
                toastr.success('Result Changed Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        printFeedback:function(id){
            let feedback = [];
            //  Fetch data using the given id
            this.$http.get('/feedback/'+id).then((response) => {
                feedback = response.data.data;
                //  Prepare PDF
                var imgData = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALwAAACuCAYAAACMV2c7AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAADUBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+Cjx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDQuMi4yLWMwNjMgNTMuMzUyNjI0LCAyMDA4LzA3LzMwLTE4OjEyOjE4ICAgICAgICAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIgogICAgeG1sbnM6eG1wUmlnaHRzPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvcmlnaHRzLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOklwdGM0eG1wQ29yZT0iaHR0cDovL2lwdGMub3JnL3N0ZC9JcHRjNHhtcENvcmUvMS4wL3htbG5zLyIKICAgeG1wUmlnaHRzOk1hcmtlZD0iRmFsc2UiCiAgIHhtcFJpZ2h0czpXZWJTdGF0ZW1lbnQ9IiIKICAgcGhvdG9zaG9wOkF1dGhvcnNQb3NpdGlvbj0iIj4KICAgPGRjOnJpZ2h0cz4KICAgIDxyZGY6QWx0PgogICAgIDxyZGY6bGkgeG1sOmxhbmc9IngtZGVmYXVsdCIvPgogICAgPC9yZGY6QWx0PgogICA8L2RjOnJpZ2h0cz4KICAgPGRjOmNyZWF0b3I+CiAgICA8cmRmOlNlcT4KICAgICA8cmRmOmxpLz4KICAgIDwvcmRmOlNlcT4KICAgPC9kYzpjcmVhdG9yPgogICA8ZGM6dGl0bGU+CiAgICA8cmRmOkFsdD4KICAgICA8cmRmOmxpIHhtbDpsYW5nPSJ4LWRlZmF1bHQiLz4KICAgIDwvcmRmOkFsdD4KICAgPC9kYzp0aXRsZT4KICAgPHhtcFJpZ2h0czpVc2FnZVRlcm1zPgogICAgPHJkZjpBbHQ+CiAgICAgPHJkZjpsaSB4bWw6bGFuZz0ieC1kZWZhdWx0Ii8+CiAgICA8L3JkZjpBbHQ+CiAgIDwveG1wUmlnaHRzOlVzYWdlVGVybXM+CiAgIDxJcHRjNHhtcENvcmU6Q3JlYXRvckNvbnRhY3RJbmZvCiAgICBJcHRjNHhtcENvcmU6Q2lBZHJFeHRhZHI9IiIKICAgIElwdGM0eG1wQ29yZTpDaUFkckNpdHk9IiIKICAgIElwdGM0eG1wQ29yZTpDaUFkclJlZ2lvbj0iIgogICAgSXB0YzR4bXBDb3JlOkNpQWRyUGNvZGU9IiIKICAgIElwdGM0eG1wQ29yZTpDaUFkckN0cnk9IiIKICAgIElwdGM0eG1wQ29yZTpDaVRlbFdvcms9IiIKICAgIElwdGM0eG1wQ29yZTpDaUVtYWlsV29yaz0iIgogICAgSXB0YzR4bXBDb3JlOkNpVXJsV29yaz0iIi8+CiAgPC9yZGY6RGVzY3JpcHRpb24+CiA8L3JkZjpSREY+CjwveDp4bXBtZXRhPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgCjw/eHBhY2tldCBlbmQ9InciPz6wCsCdAAC4aUlEQVR42uxdB5gkVbX+K3XOPTnP7GzOy7Kw5JwFWRBEgiiIgoigIij6UB9gAIkKj6cIBhAkCCgoSM5x2ZxndydPz0znUF35nVs98AizMz3DkpTer7Z7uqsr3Puf//zn3HNvc5Zl4dPHp4//lIc42pscx32S7qGci/3U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";

                var doc = new jsPDF();

                doc.setFontSize(10);
                //  Report header
                doc.addImage(imgData, 'JPEG', 90, 20, 30, 30);
                doc.writeText(0, 55, "MINISTRY OF HEALTH", { align: 'center' });
                doc.setFontSize(9);
                doc.writeText(0, 60, "NATIONAL PUBLIC HEALTH LABORATORY SERVICES (NPHLS)", { align: 'center' });
                doc.setFontSize(8);
                doc.writeText(0, 65, "NATIONAL HIV REFERENCE LABORATORY (NPHL)", { align: 'center' });
                doc.setFontSize(7);
                doc.writeText(0, 70, "P.O. BOX 20750 - 00202, NAIROBI", { align: 'center' });
                doc.setFontSize(10);
                doc.writeText(0, 80, "NATIONAL HIV SEROLOGY PROFICIENCY TESTING SCHEME", { align: 'center' });
                doc.setFontSize(7);
                doc.writeText(0, 80, "Final Report", { align: 'center' });
                //  User details area
                doc.setFontType("bold");
                doc.writeText(22.5, 85, "Round: ");
                doc.writeText(22.5, 90, "ID No.: ");
                doc.writeText(22.5, 95, "Tester: ");
                doc.writeText(22.5, 100, "Employee No.: ");
                doc.writeText(115, 85, "Program: ");
                doc.writeText(115, 90, "County.: ");
                doc.writeText(115, 95, "Sub County: ");
                doc.writeText(115, 100, "Facility: ");

                doc.setFontType("normal");
                doc.writeText(50, 85, feedback.round);
                doc.text(50, 90, feedback.uid);
                doc.writeText(50, 95, feedback.tester + "*");
                //doc.writeText(22.5, 100, "Employee No.: ");
                doc.writeText(142.5, 85, feedback.program);
                doc.writeText(142.5, 90, feedback.county);
                doc.writeText(142.5, 95, feedback.sub_county);
                var splitTitle = doc.splitTextToSize(feedback.facility, 45);
                doc.text(142.5, 100, splitTitle);
                //  Results area
                doc.writeText(22.5, 110, "RE: Proficiency Testing Results");
                doc.line(22.5, 112, 75, 112);
                doc.setFontType("normal");
                doc.writeText(22.5, 117, "NPHL acknowledges receipt of your Proficiency Testing results for Round " + feedback.round);
                doc.writeText(22.5, 122, "Your result is ")
                doc.setFontType("bolditalic");
                doc.text(45, 122, feedback.verdict);
                doc.setFontType("normal");
                doc.writeText(22.5, 127, feedback.remark);

                //results table


                //  Footer
                doc.writeText(22.5, 257, "Thank you for your participation.");
                doc.writeText(22.5, 265, "NPHL HEAD:     Nancy Bowen");
                doc.writeText(90, 265, "Signature: ");
                doc.writeText(135, 265, "Date authorized:     " + feedback.date_authorized);
                doc.writeText(22.5, 270, "NPHL Doc No 105 V:0 ");
                window.open(doc.output('bloburl'), '_blank');
            }, (response) => {
                
            });
        },
        /*Function to toggle input fields to specify value if 'other' is selected*/
        remark: function(className, obj)
        {
            var $input = $(obj);
            if($input.val() == 4)
                $(className).show();
            else
                $(className).hide();
        },

        moment: function (date) {
            return moment(date);
        },

        search: function() {
            // Clear the error message.
            this.error = '';
            // Empty the results array so we can fill it with the new results.
            this.results = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_result?q=' + this.query).then((response) => {
                // If there was an error set the error message, if not fill the results array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                { 
                    this.results = response.data.data.data;
                    // this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.query = '';
            });
        },
        filter: function(page) {
            // Clear the error message.
            this.error = '';
            // Empty the results array so we can fill it with the new results.
            this.results = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;
            var link = '/api/search_result?page='+page;

            //if county
            if (this.facility) {
                link = link +'&facility='+this.facility;

            }else if (this.sub_county) {
                link = link +'&sub_county='+this.sub_county;
            

            }else if (this.county) {

                link= link +'&county='+this.county;
            }

            if (this.result_status>=0) {
            console.log(this.result_status);
               link = link +'&result_status='+this.result_status;

            }

            if (this.feedback_status>=0) {
               link = link +'&feedback_status='+this.feedback_status;

            }

            // Making a get request to our API and passing the query to it.
            this.$http.get(link).then((response) => {
                // If there was an error set the error message, if not fill the results array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                { 
                    this.results = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.filters = 1;
            });
        },
        //Populate counties from FacilityController
        loadCounties: function() {
            this.$http.get('/cnts').then((response) => {
                this.counties = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        fetchSubs: function() {
            let id = $('#county_id').val();
            this.$http.get('/subs/'+ id).then((response) => {
                this.subs = response.data;
            }, (response) => {
                // console.log(response);
            });
        },
        fetchFacilities: function() {
            let id = $('#sub_id').val();
            this.$http.get('/fclts/'+id).then((response) => {
                this.facilities = response.data;
            }, (response) => {
                // console.log(response);
            });
        },
        //  toggle other

        //  Normal js
        specToggle(className, id){
            var $input = $("#field_"+id);
            if($input.val() != 4)
                $(className).hide();
            else
                $(className).show();
        },
        toggle_checkboxes: function() {
            var checkedNum = $('.unsatisfactory_group:checked').length;
             if (!checkedNum) {
                $('#satisfactory').prop('checked',true);

            }else if(checkedNum>0){

                $('#unsatisfactory').prop('checked',true);
                  
            }
        },
        toggle_selects: function() {
            
            // // console.log($('#feedback_status_id').val());
            // if (($('#feedback_status_id').val() != null) || ($('#feedback_status_id').val() != 4)) {
            //     $('#result_status_id').prop('disabled','disabled');
            //     this.result_status = '';                   
            // }
            //  if ($('#result_status_id').val() != null || $('#result_status_id').val() != 4) {
            //     $('#feedback_status_id').prop('disabled','disabled');
            //     this.feedback_status = '';               
            // }                
        },
    },
});
