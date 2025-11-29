import { defineConfig } from 'vite';
import { resolve } from 'path';
import { copyFileSync, existsSync } from 'fs';
import inject from '@rollup/plugin-inject';

export default defineConfig({
    base: './',

    server: {
        port: 3000,
        open: false,
    },

    css: {
        preprocessorOptions: {
            less: {
                paths: [
                    resolve(__dirname, 'src'),
                    resolve(__dirname, 'node_modules'),
                ],
                // IMPORTANTE: deixe o Vite gerenciar URLs
                rewriteUrls: 'local',
                javascriptEnabled: true,
                math: 'always',
            }
        },
        postcss: {
            plugins: [
                require('autoprefixer')({
                    overrideBrowserslist: [
                        '> 1%',
                        'last 2 versions',
                        'not dead',
                        'not IE 11',
                    ]
                }),
            ]
        }
    },

    build: {
        outDir: 'dist',
        emptyOutDir: true,
        sourcemap: true,
        minify: 'terser',

        terserOptions: {
            compress: {
                drop_console: false,
                drop_debugger: false,
            }
        },

        manifest: false,

        rollupOptions: {
            input: {
                // Arquivo principal que importa tudo
                main: resolve(__dirname, 'src/main.less'),
                // Entrada principal do tema (JS)  
                app: resolve(__dirname, 'src/main.js'),
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    if (chunkInfo.name === 'app') {
                        return 'js/theme.min.js';
                    }
                    return 'js/[name].min.js';
                },
                chunkFileNames: 'js/chunks/[name].min.js',

                // Organiza assets por tipo
                assetFileNames: (assetInfo) => {
                    const ext = assetInfo.name.split('.').pop();

                    if (ext === 'css') {
                        // Gera main.min.css conforme esperado pelo header.php
                        return 'css/main.min.css';
                    }
                    if (/png|jpe?g|svg|gif|webp|ico/i.test(ext)) {
                        return 'images/[name][extname]';
                    }
                    if (/woff2?|eot|ttf|otf/i.test(ext)) {
                        return 'fonts/[name][extname]';
                    }

                    return 'assets/[name][extname]';
                },
            }
        },

        // Aumenta o limite de aviso para assets grandes
        chunkSizeWarningLimit: 1000,
    },

    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
            '@images': resolve(__dirname, 'src/images'),
            '@fonts': resolve(__dirname, 'src/fonts'),
        }
    },

    // IMPORTANTE: Assets que NÃO são importados no código vão aqui
    publicDir: resolve(__dirname, 'public'),

    plugins: [
        // Injeta jQuery automaticamente em todos os módulos que usam $ ou jQuery
        inject({
            $: 'jquery',
            jQuery: 'jquery',
        }),

        // Plugin para corrigir caminhos do Semantic UI CSS
        {
            name: 'fix-semantic-ui-paths',
            enforce: 'post',
            generateBundle(options, bundle) {
                for (const fileName in bundle) {
                    const file = bundle[fileName];

                    if (fileName.endsWith('.css') && file.type === 'asset') {
                        let css = file.source.toString();

                        // APENAS corrige caminhos do Semantic UI (./themes/default/assets/fonts/)
                        css = css.replace(
                            /url\(["']?\.\/themes\/default\/assets\/fonts\/([^"')]+)["']?\)/gi,
                            'url(../fonts/$1)'
                        );

                        file.source = css;
                        console.log(`✅ Caminhos do Semantic UI corrigidos em: ${fileName}`);
                    }
                }
            }
        },

        // Plugin para copiar fontes do Semantic UI
        {
            name: 'copy-semantic-ui-fonts',
            closeBundle() {
                const outDir = resolve(__dirname, 'dist/fonts');
                const semanticFonts = resolve(__dirname, 'node_modules/semantic-ui-css/themes/default/assets/fonts');

                if (!existsSync(outDir)) {
                    mkdirSync(outDir, { recursive: true });
                }

                if (existsSync(semanticFonts)) {
                    const fs = require('fs');
                    const fonts = fs.readdirSync(semanticFonts);

                    fonts.forEach(font => {
                        const src = resolve(semanticFonts, font);
                        const dest = resolve(outDir, font);
                        copyFileSync(src, dest);
                        console.log(`📦 Fonte copiada: ${font}`);
                    });
                }
            }
        },

        {
            name: 'wordpress-theme-build',
            writeBundle(options, bundle) {
                console.log('✅ Build do tema WordPress concluído!');
                console.log('📁 Arquivos gerados em:', options.dir || 'dist/');
                console.log('🎨 CSS: dist/css/theme.min.css');
                console.log('⚙️  JS: dist/js/theme.min.js');

                // Lista todos os assets gerados
                Object.keys(bundle).forEach(fileName => {
                    if (bundle[fileName].type === 'asset') {
                        console.log(`📦 Asset: dist/${fileName}`);
                    }
                });
            }
        },

        // Plugin para copiar assets específicos do WordPress
        {
            name: 'copy-wordpress-assets',
            closeBundle() {
                const outDir = resolve(__dirname, 'dist');

                // Copia screenshot.png se existir
                const screenshot = resolve(__dirname, 'screenshot.png');
                if (existsSync(screenshot)) {
                    copyFileSync(screenshot, resolve(outDir, 'screenshot.png'));
                    console.log('📸 Screenshot copiado');
                }

                // Copia jquery-timing.min.js (script legado)
                const jqueryTiming = resolve(__dirname, 'assets/js/jquery-timing.min.js');
                if (existsSync(jqueryTiming)) {
                    const destDir = resolve(outDir, 'js');
                    if (!existsSync(destDir)) {
                        require('fs').mkdirSync(destDir, { recursive: true });
                    }
                    copyFileSync(jqueryTiming, resolve(destDir, 'jquery-timing.min.js'));
                    console.log('📦 jquery-timing.min.js copiado');
                }

                // Copia base.js (script legado)
                const baseJs = resolve(__dirname, 'assets/js/base.js');
                if (existsSync(baseJs)) {
                    const destDir = resolve(outDir, 'js');
                    if (!existsSync(destDir)) {
                        require('fs').mkdirSync(destDir, { recursive: true });
                    }
                    copyFileSync(baseJs, resolve(destDir, 'base.js'));
                    console.log('📦 base.js copiado');
                }
            }
        }
    ],

    define: {
        __DEV__: JSON.stringify(false),
    },

    optimizeDeps: {
        include: ['jquery'],
        exclude: []
    }
});