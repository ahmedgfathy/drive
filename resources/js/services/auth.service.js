import api from './api';

export default {
  login(payload) {
    return api.post('/auth/login', payload);
  },
  logout() {
    return api.post('/auth/logout');
  },
  me() {
    return api.get('/auth/me');
  },
};
