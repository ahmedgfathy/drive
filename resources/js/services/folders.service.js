import api from './api';

export default {
  children(folderId) {
    return api.get(`/folders/${folderId}/children`);
  },
  create(payload) {
    return api.post('/folders', payload);
  },
};
