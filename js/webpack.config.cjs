const config = require('flarum-webpack-config')();

// With several lazy imports, webpack's default cache group can emit a numeric chunk at the dist root, which jsDirectory() never publishes.
config.optimization.splitChunks.cacheGroups.default = false;

module.exports = config;
