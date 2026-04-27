import api from './api';

export default {
  mine() {
    return api.get('/shares/me');
  },
  create(payload) {
    return api.post('/shares', payload);
  },
  policy() {
    return api.get('/shares/policy');
  },
  targets(params = {}) {
    return api.get('/shares/targets', { params });
  },
  list(params = {}) {
    return api.get('/shares/me', { params });
  },
  publicShow(token, params = {}) {
    return api.get(`/public/shares/${token}`, { params });
  },
  publicFolder(token, folderId, params = {}) {
    return api.get(`/public/shares/${token}/folders/${folderId}`, { params });
  },
  publicDownload(token, fileId = null, params = {}) {
    const path = fileId
      ? `/public/shares/${token}/files/${fileId}/download`
      : `/public/shares/${token}/download`;

    return api.get(path, {
      params,
      responseType: 'blob',
    });
  },
};
