module.exports = {
  extends: '@silverstripe/eslint-config',
  // Allows null coalescing and optional chaining operators.
  parserOptions: {
    ecmaVersion: 2020
  },
  // React comes from silverstripe/admin at runtime, so eslint-plugin-react cannot detect it
  settings: {
    react: {
      version: '18.2',
    },
  },
};
