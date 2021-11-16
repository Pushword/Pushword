const path = require("path");

module.exports = {
    entry: {
        Image: "./src/Image/Image.js",
        Attaches: "./src/Attaches/Attaches.js",
        Gallery: "./src/Gallery/Gallery.js",
        Embed: "./src/Embed/Embed.js",
        PagesList: "./src/PagesList/PagesList.js",
        Hyperlink: "./src/Hyperlink/Hyperlink.js",
        Raw: "./src/Raw/Raw.js",
        NestedList: "./src/NestedList/NestedList.js",
        Header: "./src/Header/Header.js",
        Anchor: "./src/Anchor/Anchor.js",
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: "babel-loader",
                        query: {
                            presets: ["@babel/preset-env"],
                        },
                    },
                ],
            },
            {
                test: /\.p?css$/,
                use: [
                    "style-loader",
                    "css-loader",
                    {
                        loader: "postcss-loader",
                        options: {
                            plugins: [require("postcss-nested-ancestors"), require("postcss-nested")],
                        },
                    },
                ],
            },
            {
                test: /\.(svg)$/,
                use: [
                    {
                        loader: "raw-loader",
                    },
                ],
            },
        ],
    },
    output: {
        path: path.join(__dirname, "/dist"),
        publicPath: "/",
        filename: "[name].js",
        libraryExport: "default",
        libraryTarget: "umd",
    },
};
