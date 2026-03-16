import { createApp } from 'vue';

import App from './App.vue';
import { router } from './router';
import { pinia } from './pinia';

import './styles/app.css';

createApp(App).use(pinia).use(router).mount('#app');
