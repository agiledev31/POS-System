<template>
  <div class="main-content">
    <breadcumb :page="$t('Product_purchases_report')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

     <b-col md="12" class="text-center" v-if="!isLoading">
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


    <div v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
       :group-options="{
          enabled: true,
          headerPosition: 'bottom',
        }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        :styleClass="showDropdown?'tableOne table-hover vgt-table full-height':'tableOne table-hover vgt-table non-height'"
      >
       
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Purchases_PDF()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="purchases"
              :columns="columns"
              :file-name="'purchases'"
              :file-type="'xlsx'"
              :sheet-name="'purchases'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
         
        </div>

      </vue-good-table>
    </div>

    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
         
          <!-- Supplier  -->
          <b-col md="12">
            <b-form-group :label="$t('Supplier')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Supplier')"
                v-model="Filter_Supplier"
                :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
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


          <b-col md="6" sm="12">
            <b-button
              @click="Get_Purchases(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
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
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'

export default {

  metaInfo: {
    title: "Product Purchases report"
  },
  components: { DateRangePicker },
  data() {
    return {
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
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
       rows: [{
          statut: 'Total',
         
          children: [
             
          ],
      },],
      search: "",
      totalRows: "",
      showDropdown: false,
      Filter_Supplier: "",
      Filter_warehouse: "",
      suppliers: [],
      warehouses: [],
      purchases: [],
      limit: "10",
      today_mode: true,
      to: "",
      from: "",
    };
  },
   mounted() {
    this.$root.$on("bv::dropdown::show", bvEvent => {
      this.showDropdown = true;
    });
    this.$root.$on("bv::dropdown::hide", bvEvent => {
      this.showDropdown = false;
    });
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
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
          label: this.$t("Supplier"),
          field: "provider_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
      
        {
          label: this.$t("Name_product"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Qty_purchased"),
          field: "quantity",
          type: "decimal",
          headerField: this.sumCount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total"),
          field: "total",
          type: "decimal",
          headerField: this.sumCount2,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      ];
    }
  },
  methods: {

    sumCount(rowObj) {
     
    	let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        sum += rowObj.children[i].quantity;
      }
      return sum;
    },

    
    sumCount2(rowObj) {
     
    	let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        sum += rowObj.children[i].total;
      }
      return sum;
    },



    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Purchases(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Purchases(1);
      }
    },

    //---- Event Sort change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "provider_name") {
        field = "provider_id";
      } else if (params[0].field == "warehouse_name") {
        field = "warehouse_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Purchases(this.serverParams.page);
    },

    
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Purchases(this.serverParams.page);
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },


    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_Supplier = "";
      this.Filter_warehouse = "";
      this.Get_Purchases(this.serverParams.page);
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


    //----------------------------------- purchases PDF ------------------------------\\
    Purchases_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "date", dataKey: "date" },
        { title: "Ref", dataKey: "Ref" },
        { title: "Provider", dataKey: "provider_name" },
        { title: "Warehouse", dataKey: "warehouse_name" },
        { title: "Product", dataKey: "product_name" },
        { title: "Qty", dataKey: "quantity" },
        { title: "Total", dataKey: "total" },
      ];
      pdf.autoTable(columns, self.purchases);
      pdf.text("Product Purchases report", 40, 25);
      pdf.save("Product_purchases_report.pdf");
    },


  
    //---------------------------------------- Set To Strings-------------------------\\
    setToStrings() {
      // Simply replaces null values with strings=''
      if (this.Filter_Supplier === null) {
        this.Filter_Supplier = "";
      } else if (this.Filter_warehouse === null) {
        this.Filter_warehouse = "";
      } 
    },

    
     //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Get_Purchases(1);
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


    //----------------------------------------- Get all purchases ------------------------------\\
    Get_Purchases(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      this.get_data_loaded();
      axios
        .get(
          "report/product_purchases_report?page=" +
            page +
            "&provider_id=" +
            this.Filter_Supplier +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate
        )
        .then(response => {
          this.purchases = response.data.purchases;
          this.suppliers = response.data.suppliers;
          this.warehouses = response.data.warehouses;
          this.totalRows = response.data.totalRows;
           this.rows[0].children = this.purchases;
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

  
  
  
  },
  //----------------------------- Created function-------------------\\
  created() {
    this.Get_Purchases(1);
  }
};
</script>

