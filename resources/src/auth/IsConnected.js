import Vue from "vue";
import VueCookies from "vue-cookies";
Vue.use(VueCookies);

export default (to, from, next) => {
  let accessToken = VueCookies.isKey("POS_token");
  if (accessToken) {
     next("/app/dashboard");
  } else {
    return next();
  }
};
