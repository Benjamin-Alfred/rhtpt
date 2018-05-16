Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");
Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});
new Vue({

    el: '#manage-participant',

    data: {
        users: [],
        roles: [],
        counties: [],
        subs: [],
        programs:[],
        facilities: [],
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
        newUser : {'first_name':'','middle_name':'','last_name':'','name':'','username': '','role': '','gender':'', 'phone':'', 'email':'', 'address':''},
        fillUser : {'first_name':'','middle_name':'','last_name':'','name':'','username': '','role': '','gender':'', 'phone':'', 'email':'', 'address':'', 'id':'','designation':'','county':'','sub_county':'','mfl_code':'','facility':'',},
        transferUser : {'facility_id':'','program_id':'', 'id':''},
        loading: false,
        error: false,
        query: '',
        facility: '',
        sub_county: '',
        county: '',
        role: '',
        tier: '',
        total_users: '',
        active_users: '',
        inactive_users: '',
        users_without_mfl: '',
        formTransErrors:{},
        uploadify: {id: '', excel: ''},
        someUser : {'first_name':'','middle_name':'','last_name':'','name':'','gender':'', 'phone':'', 'email':'', 'address':'', 'id':'', 'county':'', 'sub_county':'', 'mfl':'', 'facility':'', 'program':'', 'designation':''},
        jimbo: [],
        sexes: [],
        uploadify: {excel: ''},
        upload: {list: ''},
        designations: [],
        reasons: { 'reason': '' }
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
        this.getVueUsers(this.pagination.current_page);
        this.loadPrograms();
        this.loadCounties();
        this.loadRoles();
        this.loadSexes();
        this.loadDesignations();
    },

    methods : {

        getVueUsers: function(page){
            this.$http.get('/vueparticipants?page='+page).then((response) => {
                if(response.data.data)
                {
                this.users = response.data.data.data;
                this.role = response.data.role;
                this.tier = response.data.tier;
                this.total_users = response.data.total_users;
                this.active_users = response.data.active_users;
                this.inactive_users = response.data.inactive_users;
                this.users_without_mfl = response.data.users_without_mfl;
                this.pagination = response.data.pagination;

                if (this.role == 4) {
                    let id = this.tier;
                    this.$http.get('/subs/'+id).then((response) => {
                        this.subs = response.data;
                    }, (response) => {
                        // console.log(response);
                    });
                }
                if (this.role == 7) {
                    let id =this.tier;
                    this.$http.get('/fclts/'+id).then((response) => {
                        this.facilities = response.data;
                    }, (response) => {
                        // console.log(response);
                    });
                }
            }
            else
            {
                swal("No data found for Participants.","","info");
            }
            });
        },

        createUser: function(scope){
            this.$validator.validateAll(scope).then(() => {
                var input = this.newUser;
                this.$http.post('/vueparticipants',input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    this.newUser = {'first_name':'','middle_name':'','last_name':'','name':'', 'username': '','gender':'', 'phone':'', 'email':'', 'address':''};
                    $("#create-user").modal('hide');
                    toastr.success('User Created Successfully.', 'Success Alert', {timeOut: 5000});
                }, (response) => {
                    this.formErrors = response.data;
                });
            }).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
                return false;
            });
        },

        deleteUser: function(user){
            this.$http.delete('/vueparticipants/'+user.id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('User Deleted Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        restoreUser: function(user){
            this.$http.patch('/vueparticipants/'+user.id+'/restore').then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('User Restored Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        editUser: function(user){
            this.fillUser.id = user.id;
            this.fillUser.first_name = user.first_name;
            this.fillUser.middle_name = user.middle_name;
            this.fillUser.last_name = user.last_name;
            this.fillUser.name = user.name;
            this.fillUser.username = user.username;
            this.fillUser.gender = user.gender;
            this.fillUser.phone = user.phone;
            this.fillUser.email = user.email;
            this.fillUser.address = user.address;
            this.fillUser.rl = user.rl;
            this.fillUser.role = user.role;
            this.fillUser.program_id = user.program;
            this.fillUser.uid = user.uid;
            this.fillUser.address = user.address;
            this.fillUser.mfl_code = user.mfl;
            this.fillUser.facility = user.fac;
            this.fillUser.sub_county = user.sub;
            this.fillUser.county = user.kaunti;
            this.fillUser.designation = user.designation;
            $("#edit-user").modal('show');
        },

        updateUser: function(id, scope){
            this.$validator.validateAll(scope).then(() => {
                var input = this.fillUser;
                this.$http.put('/vueparticipants/'+id,input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    this.fillUser = {'first_name':'','middle_name':'','last_name':'','name':'','username': '','role': '','gender':'', 'phone':'', 'email':'', 'address':'', 'id':'','designation':'','county':'','sub_county':'','mfl_code':'','facility':'',};
                    $("#edit-user").modal('hide');
                    toastr.success('User Updated Successfully.', 'Success Alert', {timeOut: 5000});
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
            this.getVueUsers(page);
        },
        //Populate counties from FacilityController
        loadCounties: function() {
            this.$http.get('/cnts').then((response) => {
                this.counties = response.data;
                this.jimbo = response.data;
            }, (response) => {
                // console.log(response);
            });
        },
        // Populate subcounties from FacilityController
        loadSubcounties: function() {
            let id = $('#county').val();
            this.$http.get('/subs/'+id).then((response) => { 
                this.subcounties = response.data;
            }, (response) => {
                // console.log(response);
            });
        }, 
        // fetch subcounties in after selecting a county
        fetchSubs: function() {
            let id = $('#county_id').val();
            this.sub_county = '';
            this.facility = '';
            this.$http.get('/subs/'+id).then((response) => {
                this.subs = response.data;
            }, (response) => {
                // console.log(response);
            });
        },     
        // Populate facilities from FacilityController
        loadFacilities: function() {
            let id = $('#sub_county').val();
            this.$http.get('/fclts/'+id).then((response) => { 
                this.facilities = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        // fetch facilities in one sub county
        fetchFacilities: function() {
            let id = $('#sub_id').val();
            this.facility = '';
            this.$http.get('/fclts/'+id).then((response) => {
                this.facilities = response.data;
            }, (response) => {
                // console.log(response);
            });
        },
        // fetch facility details
        fetchFacility: function() {
            let id = $('#mfl').val();
            this.$http.get('/mfl/'+id).then((response) => {
                this.fillUser.facility = response.data.name;
                this.fillUser.sub_county = response.data.sub_county;
                this.fillUser.county = response.data.county;
                if(this.fillUser.facility.length == 0)
                    swal("Facility not found!", "Enter a valid MFL Code.", "info");
            });
        },
        //    Populate programs from ProgramController
        loadPrograms: function() {
            this.$http.get('/progs').then((response) => { 
                this.programs = response.data;
            }, (response) => {
                // console.log(response);
            });
        },
        //    Populate roles from RoleController
        loadRoles: function() {
            this.$http.get('/priv').then((response) => { 
                this.roles = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        loadSexes: function() {
            this.$http.get('/sex').then((response) => {
                this.sexes = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        loadDesignations: function() {
            this.$http.get('/des').then((response) => {
                this.designations = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        
        uploadSheet: function(){
            $("#batch-registration").modal('show');
            console.log("Live on raw.");
        },

        batchReg(){
            // this.$validator.validateAll().then(() => {
                var input = this.uploadify;
                this.$http.post('/batch/register', input).then((response) => {
                    this.uploadify = {'excel':''};
                    $("#batch-registration").modal('hide');
                    toastr.success('Data Uploaded Successfully.', 'Success Alert', {timeOut: 5000});
                    this.errors.clear();
                }, (response) => {
                    // 
                });
            /*}).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
            });*/
        },

        fileChanged(e){
            console.log(e.target.files[0]);
            var fileReader = new FileReader();
            fileReader.readAsDataURL(e.target.files[0]);
            fileReader.onload = (e) => {
                this.uploadify.excel = e.target.result;
            }
        },

        importUsers(scope){
            this.$validator.validateAll(scope).then(() => {
                var input = this.upload;
                this.$http.post('/import/participants', input).then((response) => {
                    this.upload = {'list':''};
                    $("#import-users-list").modal('hide');
                    toastr.success('Data Uploaded Successfully.', 'Success Alert', {timeOut: 5000});
                    this.errors.clear();
                }, (response) => {
                    // 
                });
            }).catch(() => {
                toastr.error('Please upload a file.', 'Validation Failed', {timeOut: 5000});
            });
        },
        listChanged(e){
            console.log(e.target.files[0]);
            var fileReader = new FileReader();
            fileReader.readAsDataURL(e.target.files[0]);
            fileReader.onload = (e) => {
                this.upload.list = e.target.result;
            }
        },

        search: function() {
            // Clear the error message.
            this.error = '';
            // Empty the users array so we can fill it with the new users.
            this.users = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_participant?q=' + this.query).then((response) => {
                // If there was an error set the error message, if not fill the users array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                {
                    this.users = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.query = '';
            });
        },

        filter: function() {
            // Clear the error message.
            this.error = '';
            // Empty the users array so we can fill it with the new users.
            this.users = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_participant?q=' + this.query).then((response) => {
                // If there was an error set the error message, if not fill the users array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                {
                    this.users = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.query = '';
            });
        },
        no_mfl: function() {
            // Clear the error message.
            this.error = '';
            // Empty the users array so we can fill it with the new users.
            this.users = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_participant?no_mfl='+ 'no_mfl').then((response) => {
                // If there was an error set the error message, if not fill the users array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                {
                    this.users = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
                // Clear the query.
                this.query = '';
            });
        },

        filter_by_region: function() {
            // Clear the error message.
            this.error = '';
            // Empty the users array so we can fill it with the new users.
            this.users = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
             //get users filtered by facility
             if (this.facility) {
                this.$http.get('/api/search_participant?facility='+ this.facility).then((response) => {
                    // If there was an error set the error message, if not fill the users array.
                    if(response.data.error)
                    {
                        this.error = response.data.error;
                        toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                    }
                    else
                    {
                        this.users = response.data.data.data;
                        this.pagination = response.data.pagination;
                        toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                    }
                    // The request is finished, change the loading to false again.
                    this.loading = false;
                });
            }
            
            //get users filtered by sub county

            else if (this.sub_county) {
                this.$http.get('/api/search_participant?sub_county='+ this.sub_county).then((response) => {
                    // If there was an error set the error message, if not fill the users array.
                    if(response.data.error)
                    {
                        this.error = response.data.error;
                        toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                    }
                    else
                    {
                        this.users = response.data.data.data;
                        this.pagination = response.data.pagination;
                        toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                    }
                    // The request is finished, change the loading to false again.
                    this.loading = false;

                });
            }

            //get users filtered by county

            else if (this.county) {
                this.$http.get('/api/search_participant?county=' + this.county ).then((response) => {
                    // If there was an error set the error message, if not fill the users array.
                    if(response.data.error)
                    {
                        this.error = response.data.error;
                        toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                    }
                    else
                    {
                        this.users = response.data.data.data;
                        this.pagination = response.data.pagination;
                        toastr.success('The search results below were obtained.', 'Search Notification', {timeOut: 5000});
                    }
                    // The request is finished, change the loading to false again.
                    this.loading = false;
                });
            }

            //get default users
            else{
                this.getVueUsers(this.pagination.current_page);
                // The request is finished, change the loading to false again.
                this.loading = false;
            }
           
        },

        populateUser: function(user){
            this.transferUser.id = user.id;
            this.transferUser.facility_id = user.facility;
            this.transferUser.program_id = user.program;
            $("#transfer-user").modal('show');
        },

        transUser: function(id){
            var input = this.transferUser;
            this.$http.put('/transfer/'+id,input).then((response) => {
                this.changePage(this.pagination.current_page);
                this.transferUser = {'facility_id':'', 'program_id':'','id': ''};
                $("#transfer-user").modal('hide');
                toastr.success('User Updated Successfully.', 'Success Alert', {timeOut: 5000});
            }, (response) => {
                this.formTransErrors = response.data;
            });
        },

        registered: function() {
            // Clear the error message.
            this.error = '';
            // Empty the users array so we can fill it with the new users.
            this.users = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_participant?filter_by_region=' + 'self').then((response) => {
                // If there was an error set the error message, if not fill the users array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Error Notification', {timeOut: 5000});
                }
                else
                {
                    this.users = response.data.data.data;
                    this.pagination = response.data.pagination;
                    toastr.success('The results below were obtained.', 'Filter_by_region Notification', {timeOut: 5000});
                }
                // The request is finished, change the loading to false again.
                this.loading = false;
            });
        },

        openUser: function(user){
            this.someUser.id = user.id;
            this.someUser.first_name = user.first_name;
            this.someUser.middle_name = user.middle_name;
            this.someUser.last_name = user.last_name;
            this.someUser.name = user.name;
            this.someUser.sex = user.gndr;
            this.someUser.phone = user.phone;
            this.someUser.email = user.email;
            this.someUser.address = user.address;
            this.someUser.county = user.kaunti;
            this.someUser.sub_county = user.sub;
            this.someUser.mfl = user.mfl;
            this.someUser.facility = user.fac;
            this.someUser.program = user.prog;
            this.someUser.designation = user.des;
            this.someUser.status = user.status;
            $("#approve-user").modal('show');
        },

        approveUser: function(user){
            this.$http.put('/approve/'+user.id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('User Approved Successfully.', 'Success Alert', {timeOut: 5000});
            }, (response) => {
                // 
            });
        },

        denyUser: function(id){
            $(document).off('focusin.modal');

            // var input = this.someUser;
            swal({
                    title: "Enter reason for rejection.",
                    type: "input",
                    showCancelButton: true,
                    closeOnConfirm: true,
                    animation: "slide-from-top",
                    inputPlaceholder: "e.g. 123"
                },
                function(reason)
                {
                    if (reason === false) return false;
                  
                    if (reason.length == 0) {
                        swal.showInputError("Please enter the reason for rejection.");
                        return false
                    }
                    else
                    {    
                        this.reasons.reason = reason;  
                        var input = this.reasons;

                        this.$http.put('/denyUserVerification/'+id, input).then((response) => {
                            this.changePage(this.pagination.current_page);
                            this.someUser = {'first_name':'','middle_name':'','last_name':'','name':'','gender':'', 'phone':'', 'email':'', 'address':'', 'id':'', 'county':'', 'sub_county':'', 'mfl':'', 'facility':'', 'program':'', 'designation':''},
                            $("#approve-user").modal('hide');
                            toastr.success('PT participation request successfully rejected.', 'Success Alert', {timeOut: 5000});
                        });
                    }
                }.bind(this)
            );
            $(document).on('focusin.modal');
        },

        
    }
});