<template>
  <div class="main-content">
    <breadcumb :page="$t('email_templates')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else> 

        <!-- Notification Client -->
      <div class="row mt-5">
        <div class="col-md-12">

          <div class="card">
            <div class="card-header">
              <h4>{{$t('Notification_Client')}}</h4>
            </div>
            <!--begin::form-->
            <div class="card-body">

              <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

                <!-- Sell -->
                <b-tab :title="$t('Sale')">
                  <form @submit.prevent="update_custom_email('sale')">
                    <div class="row">
                      <div class=" col-md-12">
                        <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                        <p>
                          {contact_name},{business_name},{invoice_number},{invoice_url},{total_amount},{paid_amount},{due_amount}
                        </p>
                      </div>
                      <hr>

                      <div class="form-group col-md-12">
                          <label for="email_subject_sale">{{$t('Email_Subject')}} </label>
                          <input type="text" v-model="sale.subject" class="form-control"
                            name="email_subject_sale" id="email_subject_sale" :placeholder="$t('Email_Subject')">
                      </div>
                      <div class="form-group col-md-12">
                        <label for="email_body_sale">{{$t('Email_body')}} </label>
                        <vue-editor id="editor_sale" v-model="sale.body" :editor-toolbar="customToolbar"></vue-editor>
                      </div>

                    </div>

                    <div class="row mt-3">
                      <div class="col-md-6">
                        <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                          <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                        </button>
                      </div>
                    </div>
                  </form>

                </b-tab>

                <!-- Quotation -->
                <b-tab :title="$t('Quote')">

                  <form @submit.prevent="update_custom_email('quotation')">
                    <div class="row">
                      <div class=" col-md-12">
                        <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                        <p>
                          {contact_name},{business_name},{quotation_number},{quotation_url},{total_amount}
                        </p>
                      </div>
                      <hr>

                      <div class="form-group col-md-12">
                          <label for="email_subject_quotation">{{$t('Email_Subject')}} </label>
                          <input type="text" v-model="quotation.subject" class="form-control"
                            name="email_subject_quotation" id="email_subject_quotation" :placeholder="$t('Email_Subject')">
                      </div>

                      <div class="form-group col-md-12">
                        <label for="email_body_quotation">{{$t('Email_body')}} </label>
                        <vue-editor id="editor_quotation" v-model="quotation.body" :editor-toolbar="customToolbar"></vue-editor>
                      </div>

                    </div>

                    <div class="row mt-3">
                      <div class="col-md-6">
                        <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                          <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                        </button>
                      </div>
                    </div>
                  </form>

                </b-tab>

                <!-- Payment Received -->
                <b-tab :title="$t('PaiementsReceived')">

                  <form @submit.prevent="update_custom_email('payment_received')">
                    <div class="row">
                      <div class=" col-md-12">
                        <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                        <p>
                          {contact_name},{business_name},{payment_number},{paid_amount}
                        </p>
                      </div>
                      <hr>
                      <div class="form-group col-md-12">
                          <label for="email_subject_payment_received">{{$t('Email_Subject')}} </label>
                          <input type="text" v-model="payment_received.subject" class="form-control"
                            name="email_subject_payment_received" id="email_subject_payment_received" :placeholder="$t('Email_Subject')">
                      </div>

                      <div class="form-group col-md-12">
                        <label for="email_body_payment_received">{{$t('Email_body')}} </label>
                        <vue-editor id="editor_payment_received" v-model="payment_received.body" :editor-toolbar="customToolbar"></vue-editor>
                      </div>

                    </div>

                    <div class="row mt-3">
                      <div class="col-md-6">
                        <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                          <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                        </button>
                      </div>
                    </div>
                  </form>

                </b-tab>

              </b-tabs>


            </div>
          </div>
        </div>
      </div>


      <!-- {{-- Notification Supplier --}} -->
      <div class="row mt-5">
        <div class="col-md-12">

          <div class="card">
            <div class="card-header">
              <h4>{{$t('Notification_Supplier')}}</h4>
            </div>
            <!--begin::form-->
            <div class="card-body">

              <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

                <!-- Purchase -->
                <b-tab :title="$t('Purchase')">

                  <form @submit.prevent="update_custom_email('purchase')">
                    <div class="row">
                      <div class=" col-md-12">
                        <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                        <p>
                          {contact_name},{business_name},{invoice_number},{invoice_url},{total_amount},{paid_amount},{due_amount}
                        </p>
                      </div>
                      <hr>
                      <div class="form-group col-md-12">
                          <label for="email_subject_purchase">{{$t('Email_Subject')}} </label>
                          <input type="text" v-model="purchase.subject" class="form-control"
                            name="email_subject_purchase" id="email_subject_purchase" :placeholder="$t('Email_Subject')">
                      </div>

                      <div class="form-group col-md-12">
                        <label for="email_body_purchase">{{$t('Email_body')}} </label>
                        <vue-editor id="editor_purchase" v-model="purchase.body" :editor-toolbar="customToolbar"></vue-editor>
                      </div>

                    </div>

                    <div class="row mt-3">
                      <div class="col-md-6">
                        <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                          <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                        </button>
                      </div>
                    </div>
                  </form>

                </b-tab>

                <!-- Payment Sent -->
                <b-tab :title="$t('PaiementsSent')">
                  
                  <form @submit.prevent="update_custom_email('payment_sent')">
                    <div class="row">
                      <div class=" col-md-12">
                        <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                        <p>
                          {contact_name},{business_name},{payment_number},{paid_amount}
                        </p>
                      </div>
                      <hr>
                      <div class="form-group col-md-12">
                          <label for="email_subject_payment_sent">{{$t('Email_Subject')}} </label>
                          <input type="text" v-model="payment_sent.subject" class="form-control"
                            name="email_subject_payment_sent" id="email_subject_payment_sent" :placeholder="$t('Email_Subject')">
                      </div>

                      <div class="form-group col-md-12">
                        <label for="email_body_payment_sent">{{$t('Email_body')}} </label>
                        <vue-editor id="editor_payment_sent" v-model="payment_sent.body" :editor-toolbar="customToolbar"></vue-editor>
                      </div>

                    </div>

                    <div class="row mt-3">
                      <div class="col-md-6">
                        <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                          <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                            aria-hidden="true"></span> <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                        </button>
                      </div>
                    </div>
                  </form>

                </b-tab>

              </b-tabs>

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

import { VueEditor } from "vue2-editor";


export default {
   components: {
    VueEditor,
  },
  metaInfo: {
    title: "Email Templates"
  },
 
  data() {
    return {
      isLoading: true,
      Submit_Processing :false,
      sale:{
        subject:'',
        body:'',
      },
      quotation:{
        subject:'',
        body:'',
      },
      payment_received:{
        subject:'',
        body:'',
      },
      purchase:{
        subject:'',
        body:'',
      },
      payment_sent:{
        subject:'',
        body:'',
      },

      custom_email_body:'',
      custom_email_subject:'',
      customToolbar: [
        ["bold", "italic", "underline"],
        [{ list: "ordered" }, { list: "bullet" }],
      ],

     
    };
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    
      //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

     //---------------------------------- update_custom_email ----------------\\
          update_custom_email(email_type) {
              this.Submit_Processing = true;
              NProgress.start();
              NProgress.set(0.1);

              if(email_type == 'sale'){
                this.custom_email_body = this.sale.body;
                this.custom_email_subject =  this.sale.subject;
              }else if(email_type == 'quotation'){
                this.custom_email_body = this.quotation.body;
                this.custom_email_subject =  this.quotation.subject;
              }else if(email_type == 'payment_received'){
                this.custom_email_body = this.payment_received.body;
                this.custom_email_subject =  this.payment_received.subject;
              }else if(email_type == 'purchase'){
                this.custom_email_body = this.purchase.body;
                this.custom_email_subject =  this.purchase.subject;
              }else if(email_type == 'payment_sent'){
                this.custom_email_body = this.payment_sent.body;
                this.custom_email_subject =  this.payment_sent.subject;
              }
              
              axios.put("/update_custom_email", {
                custom_email_body: this.custom_email_body,
                custom_email_subject: this.custom_email_subject,
                email_type: email_type
              }, {
                headers: {
                  'Content-Type': 'text/html'
                }
              })
              .then(response => {
                 Fire.$emit("Event_email");
                 this.makeToast(
                  "success",
                  this.$t("Successfully_Updated"),
                  this.$t("Success")
                );
                NProgress.done();
                this.Submit_Processing = false;
              })
              .catch(error => {
                NProgress.done();
               this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
                this.Submit_Processing = false;
              });
          },

   

     //---------------------------------- get_emails_template ----------------\\
    get_emails_template() {
      axios
        .get("get_emails_template")
        .then(response => {
          this.sale = response.data.sale;
          this.quotation = response.data.quotation;
          this.payment_received = response.data.payment_received;
          this.purchase = response.data.purchase;
          this.payment_sent = response.data.payment_sent;

          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },   


   
  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.get_emails_template();


    Fire.$on("Event_email", () => {
      this.get_emails_template();
    });
  }
};
</script>