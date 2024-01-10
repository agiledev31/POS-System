<template>
  <div class="main-content">
    <breadcumb :page="$t('product_report')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-row v-if="!isLoading">

      <b-col md="12" class="text-center">
        <date-range-picker 
          v-model="dateRange" 
          :startDate="startDate" 
          :endDate="endDate" 
           @update="Submit_filter_dateRange"
          :locale-data="locale" > 

          <template v-slot:input="picker" style="min-width: 350px;">
              {{ picker.startDate.toJSON().slice(0, 10)}} - {{ picker.endDate.toJSON().slice(0, 10)}}
          </template>        
        </date-range-picker>
      </b-col>

       <!-- product variant -->
       <b-col md="5" class="mt-4" v-if="product.type == 'is_variant'">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('Variant_code')}}</th>
                  <th>{{$t('Variant_Name')}}</th>
                  <th>{{$t('Variant_price')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product_variant_data in product.products_variants_data">
                  <td>{{product_variant_data.code}}</td>
                  <td>{{product_variant_data.name}}</td>
                  <td>{{currentUser.currency}} {{product_variant_data.price}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

      <b-col md="12">
        <b-card class="card mb-30" header-bg-variant="transparent ">
          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">
           

            <!-- Sales Table -->
            <b-tab :title="$t('Sales')">
              <vue-good-table
                mode="remote"
                :columns="columns_sales"
                :totalRows="totalRows_sales"
                :rows="sales"
                @on-page-change="PageChangeSales"
                @on-per-page-change="onPerPageChangeSales"
                @on-search="onSearch_sales"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                 <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
                  <i class="i-Filter-2"></i>
                  {{ $t("Filter") }}
                </b-button>

                <b-button @click="Sales_PDF()" size="sm" variant="outline-success ripple m-1">
                  <i class="i-File-Copy"></i> PDF
                </b-button>

                 <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="sales"
                    :columns="columns_sales"
                    :file-name="'product_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'product_report'"
                    >
                    <i class="i-File-Excel"></i> EXCEL
                </vue-excel-xlsx>

              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/sales/detail/'+props.row.sale_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>

                  <div v-else-if="props.column.field == 'total'">
                    <span>{{currentUser.currency}} {{props.row.total}}</span>
                  </div>

                </template>
              </vue-good-table>
            </b-tab>

          </b-tabs>
        </b-card>
      </b-col>
    </b-row>

    
    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
         
          <!-- Reference -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_Client"
                :options="customers.map(customers => ({label: customers.name, value: customers.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- warehouse -->
          <b-col md="12">
            <b-form-group :label="$t('warehouse')">
              <v-select
                v-model="Filter_warehouse"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

           <!-- Vendeur  -->
          <b-col md="12">
            <b-form-group label="Vendeur">
              <v-select
                :reduce="label => label.value"
                placeholder="Choose Vendeur"
                v-model="Filter_user"
                :options="users.map(users => ({label: users.username, value: users.id}))"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Sales(1)"
              variant="primary btn-block ripple m-1"
              size="sm"
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple btn-block m-1" size="sm">
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>


  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import jsPDF from "jspdf";
import "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Products Report"
  },
  components: { DateRangePicker },
  data() {
    return {
      totalRows_sales: "",
      limit_sales: "10",
      sales_page: 1,
      search_sales:"",

      Filter_Client: "",
      Filter_Ref: "",
      Filter_warehouse: "",
      Filter_user: "",

     
      isLoading: true,
      sales: [],
      warehouses: [],
      customers: [],
      users: [],
      product:{},
      today_mode: true,
      startDate: "", 
      endDate: "", 
      dateRange: { 
       startDate: "", 
       endDate: "" 
      }, 
      locale:{ 
          //separator between the two ranges apply
          Label: "Apply", 
          cancelLabel: "Cancel", 
          weekLabel: "W", 
          customRangeLabel: "Custom Range", 
          daysOfWeek: moment.weekdaysMin(), 
          //array of days - see moment documenations for details 
          monthNames: moment.monthsShort(), //array of month names - see moment documenations for details 
          firstDay: 1 //ISO first day of week - see moment documenations for details
        },

    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
   
    columns_sales() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
         {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left",
           sortable: false
        },
        {
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },
    
  },

  methods: {

      //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_Client = "";
      this.Filter_Ref = "";
      this.Filter_warehouse = "";
      this.Filter_user = "";
      this.Get_Sales(1);
    },


     //----------------------------------- Sales PDF ------------------------------\\
    Sales_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Ref", dataKey: "Ref" },
        { title: "Created_by", dataKey: "created_by" },
        { title: "Product Name", dataKey: "product_name" },
        { title: "Client", dataKey: "client_name" },
        { title: "Warehouse", dataKey: "warehouse_name" },
        { title: "Quantity", dataKey: "quantity" },
        { title: "SubTotal", dataKey: "total" },
      ];
      pdf.autoTable(columns, self.sales);
      pdf.text("Sale List", 40, 25);
      pdf.save("Sale_List.pdf");
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },


    //--------------------------- Event Page Change -------------\\
    PageChangeSales({ currentPage }) {
      if (this.sales_page !== currentPage) {
        this.Get_Sales(currentPage);
      }
    },

    //--------------------------- Limit Page Sales -------------\\
    onPerPageChangeSales({ currentPerPage }) {
      if (this.limit_sales !== currentPerPage) {
        this.limit_sales = currentPerPage;
        this.Get_Sales(1);
      }
    },

    onSearch_sales(value) {
      this.search_sales = value.searchTerm;
      this.Get_Sales(1);
    },

      //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Get_Sales(1);
    },


    get_data_loaded() {
      var self = this;
      if (self.today_mode) {
        let today = new Date()

        self.startDate = today.getFullYear();
        self.endDate = new Date().toJSON().slice(0, 10);

        self.dateRange.startDate = today.getFullYear();
        self.dateRange.endDate = new Date().toJSON().slice(0, 10);
        
      }
    },

       //----------------------------------- Get Details Product ------------------------------\\
      showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail/${id}`)
        .then(response => {
          this.product = response.data;
        })
        .catch(response => {
         
        });
    },

    //--------------------------- sale_products_details -------------\\
    Get_Sales(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "/report/sale_products_details?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&client_id=" +
            this.Filter_Client +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&user_id=" +
            this.Filter_user +
            "&limit=" +
            this.limit_sales +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_sales +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.sales = response.data.sales;
          this.totalRows_sales = response.data.totalRows;
          this.customers = response.data.customers;
          this.warehouses = response.data.warehouses;
          this.users = response.data.users;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
           NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

  
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.showDetails();
    this.Get_Sales(1);
  }
};
</script>