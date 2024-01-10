<template>
  <div class="main-content">
    <breadcumb :page="$t('product_report')" :folder="$t('Reports')"/>
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

      <vue-good-table
        v-if="!isLoading"
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="products"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-search="onSearch_products"
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
        styleClass="mt-5 table-hover tableOne vgt-table"
      >
      <div slot="table-actions" class="mt-2 mb-3">
        <b-button @click="export_PDF()" size="sm" variant="outline-success ripple m-1">
          <i class="i-File-Copy"></i> PDF
        </b-button>
         <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="products"
              :columns="columns"
              :file-name="'product_report'"
              :file-type="'xlsx'"
              :sheet-name="'product_report'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>

           <!-- warehouse -->
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
            />
          </b-form-group>

      </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <router-link title="Report" :to="'/app/reports/detail_product/'+props.row.id">
              <b-button variant="primary">{{$t('Reports')}}</b-button>
            </router-link>
          </span>

          <div v-else-if="props.column.field == 'sold_amount'">
            <span>{{currentUser.currency}} {{props.row.sold_amount}}</span>
          </div>
        </template>

      </vue-good-table>
      <!-- </b-card> -->
    </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Products Report"
  },
  components: { DateRangePicker },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      totalRows: "",
      products: [],
      warehouses: [],
      warehouse_id: "",
      search_products:"",
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
    columns() {
      return [
        {
          label: this.$t("ProductCode"),
          field: "code",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("ProductName"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("TotalSales"),
          field: "sold_qty",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },

        {
          label: this.$t("TotalAmount"),
          field: "sold_amount",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Action"),
          field: "actions",
          html: true,
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        }
      ];
    }
  },

  methods: {

    
    onSearch_products(value) {
      this.search_products = value.searchTerm;
      this.Get_products_report(1);
    },


    //----------------------------------- Export PDF ------------------------------\\
    export_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Product Code", dataKey: "code" },
        { title: "Product Name", dataKey: "name" },
        { title: "Total Sales", dataKey: "sold_qty" },
        { title: "Total Amount", dataKey: "sold_amount" },
      ];
      pdf.autoTable(columns, self.products);
      pdf.text("Products Report", 40, 25);
      pdf.save("Products Report.pdf");
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_products_report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_products_report(1);
      }
    },

     //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Get_products_report(1);
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

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Get_products_report(1);
    },


    //----------------------------- Get_products_report------------------\\
    Get_products_report(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "report/product_report?page=" +
            page +
            "&limit=" +
            this.limit +
            "&warehouse_id=" +
            this.warehouse_id +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_products
        )
        .then(response => {
          this.warehouses = response.data.warehouses;
          this.products = response.data.products;
          this.totalRows = response.data.totalRows;
          // Complete the animation of theprogress bar.
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
    }
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.Get_products_report(1);
  }
};
</script>