import api from './api';

export default {
  search(params) {
    return api.get('/files/search', { params });
  },
  upload(formData) {
    return api.post('/files/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  download(fileId) {
    return api.get(`/files/${fileId}/download`, { responseType: 'blob' });
  },
};
