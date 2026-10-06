import MonacoWebpackPlugin from 'monaco-editor-webpack-plugin'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default {
  entry: './index.js',
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: 'app.js',
  },
  resolve: {
    alias: {
      // monaco-worker-manager still imports the path used before Monaco 0.56.
      'monaco-editor/esm/vs/editor/editor.worker.js$':
        'monaco-editor/editor/editor.worker.js',
    },
  },
  module: {
    rules: [
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
      {
        test: /\.ttf$/,
        type: 'asset/resource',
      },
    ],
  },
  plugins: [
    new MonacoWebpackPlugin({
      languages: [
        'bash',
        'shell',
        'html',
        'javascript',
        'twig',
        'yaml',
        'php',
        'json',
        'markdown',
      ],
      customLanguages: [
        {
          label: 'yaml',
          entry: 'monaco-yaml',
          worker: {
            id: 'monaco-yaml/yamlWorker',
            entry: 'monaco-yaml/yaml.worker',
          },
        },
      ],
    }),
  ],
}
