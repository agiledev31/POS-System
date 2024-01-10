<template>
  <div class="main-content">
    <breadcumb :page="$t('sms_settings')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

     <!-- default_form_sms -->
    <validation-observer ref="default_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Default_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="default sms gateway">
              <b-card-body>
                <b-row>
                    <!-- Default SMS Gateway -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Default_SMS_Gateway')">
                      <v-select
                        v-model="default_sms_gateway"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_SMS_Gateway')"
                        :options="sms_gateway.map(sms_gateway => ({label: sms_gateway.title, value: sms_gateway.id}))"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

    <!-- Twilio SMS API -->
    <validation-observer ref="twilio_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Twilio_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="TWILIO_SMS">
              <b-card-body>
                <b-row>
                  
                   <!-- TWILIO_SID  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TWILIO_SID"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="TWILIO SID *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TWILIO_SID-feedback"
                          label="TWILIO_SID"
                          v-model="twilio.TWILIO_SID"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TWILIO_SID-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                   <!-- TWILIO_TOKEN  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="TWILIO TOKEN *">
                        <b-form-input
                          label="TWILIO_TOKEN"
                          v-model="twilio.TWILIO_TOKEN"
                          :placeholder="$t('LeaveBlank')"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- TWILIO_FROM  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TWILIO_FROM"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="TWILIO FROM *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TWILIO_FROM-feedback"
                          label="TWILIO_FROM"
                          v-model="twilio.TWILIO_FROM"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TWILIO_FROM-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>


    <!-- nexmo SMS API -->
    <validation-observer ref="nexmo_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Nexmo_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="Nexmo (now Vonage)">
              <b-card-body>
                <b-row>
                  
                   <!-- NEXMO_KEY  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="NEXMO_KEY"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="NEXMO KEY *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="NEXMO_KEY-feedback"
                          label="NEXMO_KEY"
                          v-model="nexmo.nexmo_key"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="NEXMO_KEY-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                    <!-- NEXMO_SECRET  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="NEXMO_SECRET"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="NEXMO SECRET *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="NEXMO_SECRET-feedback"
                          label="NEXMO_SECRET"
                          v-model="nexmo.nexmo_secret"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="NEXMO_SECRET-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                     <!-- SMS From  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="SMS From"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group  label="SMS From *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="NEXMO_FROM-feedback"
                          label="NEXMO_FROM"
                          v-model="nexmo.nexmo_from"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="NEXMO_FROM-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

     <!-- Infobip SMS API -->
    <validation-observer ref="infobip_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_infobip_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="InfoBip">
              <b-card-body>
                <b-row>
                  
                   <!-- BASE_URL  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="BASE URL">
                        <b-form-input
                          label="BASE_URL"
                          v-model="infobip.base_url"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- API_KEY  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group  label="API KEY">
                        <b-form-input
                          label="API_KEY"
                          v-model="infobip.api_key"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- SMS Sender From  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="SMS sender number Or Name">
                        <b-form-input
                          label="SMS_From"
                          v-model="infobip.sender_from"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              
              <p class="mt-5">
                <strong>BASE_URL : </strong> The Infobip data center used for API traffic.<br>

                <strong>API_KEY :</strong> Authentication method. See API documentation <br>

                <strong>SMS sender number Or Name :</strong> displayed on recipient's device as message sender. <br>
                
                <strong>WhatsApp sender number :</strong> Registered WhatsApp sender number. Must be in international format.<br>

                <strong> ## Links</strong><br>

                <strong>[API Reference](https://www.infobip.com/docs/api)</strong><br>

                <strong>[PHP Client for Infobip API](https://github.com/infobip/infobip-api-php-client)</strong><br>
            </p>
            </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>

      
    </validation-observer>

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "SMS Settings"
  },
  data() {
    return {
      
      isLoading: true,
      sms_gateway: [],
      default_sms_gateway:'',

      twilio:{
        TWILIO_SID:'',
        TWILIO_TOKEN:'',
        TWILIO_FROM:'',
      },

      nexmo:{
        nexmo_key:'',
        nexmo_secret:'',
        nexmo_from:'',
      },

      infobip:{
        base_url:'',
        api_key:'',
        sender_from:'',
      },
     
    };
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    
    //------------- Submit_Default_SMS
    Submit_Default_SMS() {
      this.$refs.default_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_Default_SMS();
        }
      });
    },


    //------------- Submit Validation SMS
    Submit_Twilio_SMS() {
      this.$refs.twilio_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_twilio_config();
        }
      });
    },

    //------------- Submit Validation SMS
    Submit_Nexmo_SMS() {
      this.$refs.nexmo_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_nexmo_config();
        }
      });
    },

     //------------- Submit Validation SMS
    Submit_infobip_SMS() {
      this.$refs.infobip_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_infobip_config();
        }
      });
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

      //---------------------------------- update_twilio_config ----------------\\
    update_Default_SMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .put("update_Default_SMS",{
          default_sms_gateway: this.default_sms_gateway,
        })
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },


  

     //---------------------------------- update_twilio_config ----------------\\
    update_twilio_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_twilio_config",{
          TWILIO_SID: this.twilio.TWILIO_SID,
          TWILIO_TOKEN: this.twilio.TWILIO_TOKEN,
          TWILIO_FROM: this.twilio.TWILIO_FROM,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

      //---------------------------------- update_nexmo_config ----------------\\
    update_nexmo_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_nexmo_config",{
          nexmo_key: this.nexmo.nexmo_key,
          nexmo_secret: this.nexmo.nexmo_secret,
          nexmo_from: this.nexmo.nexmo_from,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //---------------------------------- update_nexmo_config ----------------\\
    update_infobip_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_infobip_config",{
          base_url: this.infobip.base_url,
          api_key: this.infobip.api_key,
          sender_from: this.infobip.sender_from,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },



     //---------------------------------- get_sms_config ----------------\\
    get_sms_config() {
      axios
        .get("get_sms_config")
        .then(response => {
          this.twilio = response.data.twilio;
          this.nexmo = response.data.nexmo;
          this.infobip = response.data.infobip;
          this.sms_gateway = response.data.sms_gateway;
          this.default_sms_gateway = response.data.default_sms_gateway;
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
    this.get_sms_config();


    Fire.$on("Event_sms", () => {
      this.get_sms_config();
    });
  }
};
</script>