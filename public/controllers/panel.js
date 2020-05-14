Vue.http.headers.common['X-CSRF-TOKEN'] = $("#token").attr("value");
Vue.http.interceptors.unshift(function(request, next) {
    next(function(response) {
        if(typeof response.headers['content-type'] != 'undefined') {
            response.headers['Content-Type'] = response.headers['content-type'];
        }
    });
});
new Vue({

    el: '#manage-panel',

    data: {
        panels: [],
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
        newPanel : {'lot_id':'','panel':'','panel_label':'','material_id':'','result':'','prepared_by':'','tested_by':''},
        fillPanel : {'lot_id':'','panel':'','panel_label':'','material_id':'','result':'','prepared_by':'','tested_by':'','id':''},
        materials: [],
        options: [],
        lots: [],
        panels: [],
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
    	this.getVuePanels(this.pagination.current_page);
        this.loadMaterials();
        this.loadResults();
        this.loadLots();
    },

    methods : {

        getVuePanels: function(page){
            this.$http.get('/vuepanels?page='+page).then((response) => {
                if(response.data.data)
                {
                    this.panels = response.data.data.data;
                    this.pagination = response.data.pagination;
                }
                else
                {
                    swal("No data found for PT panels.", "", "info");
                }
            });
        },

        createPanel: function(scope){
            this.$validator.validateAll(scope).then(() => {
      		    var input = this.newPanel;
      		    this.$http.post('/vuepanels',input).then((response) => {
                
                if(response.data == 'error')
                {
                    this.error = response.data;
                    toastr.error(this.error, 'This Panel already exists', {timeOut: 5000});
                }
                else
                {
        		    this.changePage(this.pagination.current_page);
          			this.newPanel = {'lot_id':'','panel':'','panel_label':'','material_id':'','result':'','prepared_by':'','tested_by':''};
          			$("#create-panel").modal('hide');
          			toastr.success('Panel Created Successfully.', 'Success Alert', {timeOut: 5000});
                }
      		    }, (response) => {
      			    this.formErrors = response.data;
                });
            }).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
            });
      	},

        deletePanel: function(panel){
            this.$http.delete('/vuepanels/'+panel.id).then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Panel Deleted Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        restorePanel: function(panel){
            this.$http.patch('/vuepanels/'+panel.id+'/restore').then((response) => {
                this.changePage(this.pagination.current_page);
                toastr.success('Panel Restored Successfully.', 'Success Alert', {timeOut: 5000});
            });
        },

        editPanel: function(panel){
            this.fillPanel.id = panel.id;
            this.fillPanel.lot_id = panel.lot_id;
            this.fillPanel.panel = panel.panel;
            this.fillPanel.panel_label = panel.panel_label;
            this.fillPanel.material_id = panel.material_id;
            this.fillPanel.prepared_by = panel.prepared_by;
            this.fillPanel.result = panel.result;
            this.fillPanel.tested_by = panel.tested_by;
            $("#edit-panel").modal('show');
        },

        updatePanel: function(id, scope){
            this.$validator.validateAll(scope).then(() => {
                var input = this.fillPanel;
                this.$http.put('/vuepanels/'+id,input).then((response) => {
                    this.changePage(this.pagination.current_page);
                    this.fillPanel = {'lot_id':'','panel':'','panel_label':'','material_id':'','result':'','prepared_by':'','tested_by':'', 'id':''};
                    $("#edit-panel").modal('hide');
                    toastr.success('Panel Updated Successfully.', 'Success Alert', {timeOut: 5000});
                }, (response) => {
                    this.formErrorsUpdate = response.data;
                });
            }).catch(() => {
                toastr.error('Please fill in the fields as required.', 'Validation Failed', {timeOut: 5000});
            });
        },

        changePage: function (page) {
            this.pagination.current_page = page;
            this.getVuePanels(page);
        },

        loadMaterials: function() {
            this.$http.get('/mat').then((response) => {
                this.materials = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        loadLots: function() {
            this.$http.get('/lots').then((response) => {
                this.lots = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        loadResults: function() {
            this.$http.get('/rslts').then((response) => {
                this.options = response.data;
            }, (response) => {
                // console.log(response);
            });
        },

        search: function() {
            // Clear the error message.
            this.error = '';
            // Empty the Panels array so we can fill it with the new Panels.
            this.panels = [];
            // Set the loading property to true, this will display the "Searching..." button.
            this.loading = true;

            // Making a get request to our API and passing the query to it.
            this.$http.get('/api/search_panel?q=' + this.query).then((response) => {
                // If there was an error set the error message, if not fill the Panels array.
                if(response.data.error)
                {
                    this.error = response.data.error;
                    toastr.error(this.error, 'Search Notification', {timeOut: 5000});
                }
                else
                {
                    this.panels = response.data.data.data;
                    this.pagination = response.data.data.pagination;
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