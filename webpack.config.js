const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    entry: {
      main: './assets/js/main.js',
      chat: './assets/js/components/Chat/ChatApp.js',
      tournaments: './assets/js/components/Tournament/TournamentApp.js',
      dashboard: './assets/js/components/Dashboard/DashboardApp.js'
    },
    
    output: {
      path: path.resolve(__dirname, 'dist'),
      filename: 'js/[name].[contenthash].bundle.js',
      clean: true,
      publicPath: '/dist/'
    },
    
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.(sa|sc|c)ss$/,
          use: [
            isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
            'css-loader',
            'sass-loader'
          ]
        },
        {
          test: /\.(png|svg|jpg|jpeg|gif)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'images/[name].[contenthash][ext]'
          }
        },
        {
          test: /\.(woff|woff2|eot|ttf|otf)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'fonts/[name].[contenthash][ext]'
          }
        }
      ]
    },
    
    plugins: [
      new HtmlWebpackPlugin({
        template: './assets/templates/app.html',
        filename: 'index.html',
        chunks: ['main']
      }),
      ...(isProduction ? [
        new MiniCssExtractPlugin({
          filename: 'css/[name].[contenthash].css'
        })
      ] : [])
    ],
    
    resolve: {
      extensions: ['.js', '.jsx'],
      alias: {
        '@': path.resolve(__dirname, 'assets/js'),
        '@components': path.resolve(__dirname, 'assets/js/components'),
        '@utils': path.resolve(__dirname, 'assets/js/utils'),
        '@api': path.resolve(__dirname, 'assets/js/api'),
        '@hooks': path.resolve(__dirname, 'assets/js/hooks'),
        '@store': path.resolve(__dirname, 'assets/js/store')
      }
    },
    
    devServer: {
      static: path.resolve(__dirname, 'dist'),
      port: 3000,
      hot: true,
      proxy: {
        '/api': {
          target: 'http://localhost',
          changeOrigin: true,
          pathRewrite: {
            '^/api': '/api/v1'
          }
        },
        '/socket.io': {
          target: 'http://localhost:8080',
          ws: true
        }
      }
    },
    
    optimization: {
      splitChunks: {
        chunks: 'all',
        cacheGroups: {
          vendor: {
            test: /[\\/]node_modules[\\/]/,
            name: 'vendors',
            chunks: 'all'
          },
          react: {
            test: /[\\/]node_modules[\\/](react|react-dom)[\\/]/,
            name: 'react',
            chunks: 'all'
          }
        }
      }
    }
  };
};
