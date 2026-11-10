(function (window) {
  'use strict';

  function canUseSessionStorage() {
    try {
      var testKey = '__vc_view_state_test__';
      window.sessionStorage.setItem(testKey, '1');
      window.sessionStorage.removeItem(testKey);
      return true;
    } catch (error) {
      return false;
    }
  }

  function createKey(namespace, scope) {
    var safeNamespace = String(namespace || 'default');
    var safeScope = String(scope || window.location.pathname || 'page');

    return 'vcViewState:' + safeNamespace + ':' + safeScope;
  }

  function create(namespace, options) {
    var settings = options || {};
    var storageKey = createKey(namespace, settings.scope);
    var hasStorage = canUseSessionStorage();

    function save(payload) {
      if (!hasStorage) {
        return false;
      }

      try {
        window.sessionStorage.setItem(storageKey, JSON.stringify(payload || {}));
        return true;
      } catch (error) {
        return false;
      }
    }

    function restore() {
      if (!hasStorage) {
        return null;
      }

      try {
        return JSON.parse(window.sessionStorage.getItem(storageKey) || 'null');
      } catch (error) {
        clear();
        return null;
      }
    }

    function clear() {
      if (!hasStorage) {
        return false;
      }

      try {
        window.sessionStorage.removeItem(storageKey);
        return true;
      } catch (error) {
        return false;
      }
    }

    return {
      key: storageKey,
      save: save,
      restore: restore,
      clear: clear
    };
  }

  window.VCViewState = {
    create: create
  };
}(window));
