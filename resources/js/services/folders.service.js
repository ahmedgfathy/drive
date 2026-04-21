import api from './api';

export default {
  root() {
    return api.get('/folders/root');
  },
  tree() {
    return api.get('/folders/tree');
  },
  children(folderId) {
    return api.get(`/folders/${folderId}/children`);
  },
  create(payload) {
    return api.post('/folders', payload);
  },
  update(folderId, payload) {
    return api.patch(`/folders/${folderId}`, payload);
  },
  delete(folderId) {
    return api.delete(`/folders/${folderId}`);
  },
};
