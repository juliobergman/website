<template>
    <v-app>
        <nav-drawer></nav-drawer>
        <app-bar></app-bar>
        <v-main>
            <router-view :key="$route.path"></router-view>
        </v-main>
        <confirm ref="confirm" />
    </v-app>
</template>

<script>
import AppBar from "./ui/appBar";
import NavDrawer from "./ui/navDrawer.vue";
export default {
    components: {
        AppBar,
        NavDrawer
    },
    data: () => ({}),
    methods: {},
    created() {
        this.$store.dispatch("user/setUser");
    }
};
</script>

<style></style>
