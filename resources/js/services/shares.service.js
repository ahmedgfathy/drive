import api from './api';

export default {
  mine() {
    return api.get('/shares/me');
  },
  share(payload) {
    return api.post('/shares', payload);
  },
  list(params = {}) {
    return api.get('/shares/me', { params });
  },
};
