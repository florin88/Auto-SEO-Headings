<?php
/**
 * Plugin Name: Auto SEO Headings
 * Plugin URI: https://2088.it
 * Description: Suggerisce trasformazioni intelligenti di paragrafi in titoli H2/H3 senza modifiche automatiche distruttive.
 * Version: 2.0.0
 * Author: Flavius Florin Harabor
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-seo-headings
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class AutoSEOHeadingsNonDestructive {
    
    private $meta_key_keyword = '_auto_headings_focus_keyword';
    private $meta_key_enabled = '_auto_headings_enabled';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Registra i meta field
        $this->register_meta_fields();
        
        // Aggiungi pannello e assets nell'editor Gutenberg
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Aggiungi CSS per il frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // AJAX handlers per le trasformazioni
        add_action('wp_ajax_auto_seo_transform_block', array($this, 'handle_transform_block'));
        add_action('wp_ajax_auto_seo_get_suggestions', array($this, 'handle_get_suggestions'));
    }
    
    /**
     * Registra i meta fields personalizzati
     */
    private function register_meta_fields() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            // Keyword opzionale
            register_post_meta($post_type, $this->meta_key_keyword, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
            
            // Flag per abilitare/disabilitare il plugin per questo post
            register_post_meta($post_type, $this->meta_key_enabled, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
        }
    }
    
    /**
     * Carica gli assets per l'editor Gutenberg
     */
    public function enqueue_editor_assets() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('post', 'page'))) {
            return;
        }
        
        // Assicurati che i file esistano
        $this->ensure_assets_exist();
        
        // Registra e carica il file JavaScript
        wp_enqueue_script(
            'auto-seo-headings-editor',
            plugin_dir_url(__FILE__) . 'assets/editor.js',
            array('wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-blocks', 'wp-block-editor'),
            '2.0.0',
            true
        );
        
        // Passa dati a JavaScript
        wp_localize_script('auto-seo-headings-editor', 'autoSeoHeadingsData', array(
            'metaKeyKeyword' => $this->meta_key_keyword,
            'metaKeyEnabled' => $this->meta_key_enabled,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('auto_seo_headings_nonce'),
            'postId' => get_the_ID()
        ));
        
        // Carica CSS per l'editor
        wp_enqueue_style(
            'auto-seo-headings-editor',
            plugin_dir_url(__FILE__) . 'assets/editor.css',
            array(),
            '2.0.0'
        );
    }
    
    /**
     * Carica CSS per il frontend
     */
    public function enqueue_frontend_styles() {
        $this->ensure_assets_exist();
        wp_enqueue_style(
            'auto-seo-headings-frontend',
            plugin_dir_url(__FILE__) . 'assets/frontend.css',
            array(),
            '2.0.0'
        );
    }
    
    /**
     * Carica CSS per l'admin
     */
    public function enqueue_admin_styles() {
        $this->ensure_assets_exist();
        wp_enqueue_style(
            'auto-seo-headings-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            '2.0.0'
        );
    }
    
    /**
     * Handler AJAX per ottenere suggerimenti
     */
    public function handle_get_suggestions() {
        // Verifica nonce e permessi
        if (!wp_verify_nonce($_POST['nonce'], 'auto_seo_headings_nonce') || 
            !current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        $post_id = intval($_POST['post_id']);
        $content = wp_kses_post($_POST['content']);
        
        // Ottieni titolo e keyword
        $post = get_post($post_id);
        $post_title = $post ? $post->post_title : '';
        $user_keyword = get_post_meta($post_id, $this->meta_key_keyword, true);
        
        // Analizza il contenuto e genera suggerimenti
        $suggestions = $this->analyze_content_for_suggestions($content, $post_title, $user_keyword);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Handler AJAX per trasformare un blocco
     */
    public function handle_transform_block() {
        // Verifica nonce e permessi
        if (!wp_verify_nonce($_POST['nonce'], 'auto_seo_headings_nonce') || 
            !current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        $block_content = wp_kses_post($_POST['block_content']);
        $heading_level = intval($_POST['heading_level']);
        
        // Valida il livello del titolo
        if (!in_array($heading_level, array(2, 3))) {
            wp_send_json_error('Invalid heading level');
        }
        
        // Trasforma il contenuto preservando la formattazione HTML
        $transformed_content = $this->transform_to_heading($block_content, $heading_level);
        
        wp_send_json_success(array(
            'transformed_content' => $transformed_content
        ));
    }
    
    /**
     * Analizza il contenuto per generare suggerimenti
     */
    private function analyze_content_for_suggestions($content, $post_title, $user_keyword) {
        $blocks = parse_blocks($content);
        $suggestions = array();
        $title_keywords = $this->extract_keywords_from_title($post_title);
        $paragraph_index = 0; // Indice solo per i paragrafi
        
        foreach ($blocks as $block_index => $block) {
            if (!isset($block['blockName']) || $block['blockName'] !== 'core/paragraph') {
                continue;
            }
            
            $text_content = $this->extract_text_content($block);
            if (empty($text_content)) {
                $paragraph_index++;
                continue;
            }
            
            $text_length = mb_strlen($text_content, 'UTF-8');
            
            // Verifica se è un candidato per la trasformazione
            if ($text_length >= 8 && $text_length <= 80) {
                $suggested_level = $this->determine_heading_level($text_content, $title_keywords, $user_keyword);
                
                $suggestions[] = array(
                    'block_index' => $paragraph_index, // Usa l'indice dei paragrafi
                    'text_content' => $text_content,
                    'html_content' => $block['innerHTML'],
                    'suggested_level' => $suggested_level,
                    'length' => $text_length
                );
            }
            
            $paragraph_index++;
        }
        
        return $suggestions;
    }
    
    /**
     * Estrae le parole chiave significative dal titolo
     */
    private function extract_keywords_from_title($title) {
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/[^\w\s]/', ' ', $title);
        $words = preg_split('/\s+/', $title);
        
        // Stopwords italiane più complete
        $stopwords = array(
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'di', 'a', 'da', 'in', 'con', 'su', 'per',
            'tra', 'fra', 'e', 'ed', 'o', 'od', 'che', 'chi', 'cui', 'come', 'quando', 'dove', 'mentre',
            'se', 'anche', 'ancora', 'più', 'molto', 'tutto', 'tutti', 'tutte', 'ogni', 'questo', 'questa',
            'questi', 'queste', 'quello', 'quella', 'quelli', 'quelle', 'del', 'dello', 'della', 'dei',
            'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'dal', 'dallo', 'dalla', 'dai',
            'dagli', 'dalle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle', 'sul', 'sullo', 'sulla',
            'sui', 'sugli', 'sulle', 'essere', 'avere', 'fare', 'dire', 'andare', 'potere', 'dovere',
            'volere', 'sapere', 'dare', 'stare', 'venire', 'uscire', 'parlare', 'vedere', 'cosa', 'non',
            'ma', 'però', 'quindi', 'poi', 'già', 'ancora', 'sempre', 'mai', 'oggi', 'ieri', 'domani'
        );
        
        $keywords = array_filter($words, function($word) use ($stopwords) {
            return mb_strlen($word, 'UTF-8') >= 3 && !in_array($word, $stopwords);
        });
        
        return array_values($keywords);
    }
    
    /**
     * Determina il livello del titolo suggerito
     */
    private function determine_heading_level($text, $title_keywords, $user_keyword) {
        $text_lower = mb_strtolower($text, 'UTF-8');
        
        // Priorità 1: keyword utente
        if (!empty($user_keyword) && stripos($text_lower, mb_strtolower($user_keyword, 'UTF-8')) !== false) {
            return 2;
        }
        
        // Priorità 2: parole chiave del titolo
        foreach ($title_keywords as $keyword) {
            if (stripos($text_lower, $keyword) !== false) {
                return 2;
            }
        }
        
        return 3;
    }
    
    /**
     * Calcola la confidenza del suggerimento (0-100)
     */
    private function calculate_confidence($text, $title_keywords, $user_keyword) {
        $confidence = 50; // Base confidence
        $text_lower = mb_strtolower($text, 'UTF-8');
        
        // Aumenta confidenza se contiene keyword utente
        if (!empty($user_keyword) && stripos($text_lower, mb_strtolower($user_keyword, 'UTF-8')) !== false) {
            $confidence += 30;
        }
        
        // Aumenta confidenza per ogni parola chiave del titolo trovata
        foreach ($title_keywords as $keyword) {
            if (stripos($text_lower, $keyword) !== false) {
                $confidence += 15;
            }
        }
        
        // Aggiusta per lunghezza ottimale
        $length = mb_strlen($text, 'UTF-8');
        if ($length >= 15 && $length <= 50) {
            $confidence += 10;
        }
        
        return min(100, $confidence);
    }
    
    /**
     * Ottieni le ragioni del suggerimento
     */
    private function get_suggestion_reasons($text, $title_keywords, $user_keyword) {
        $reasons = array();
        $text_lower = mb_strtolower($text, 'UTF-8');
        
        if (!empty($user_keyword) && stripos($text_lower, mb_strtolower($user_keyword, 'UTF-8')) !== false) {
            $reasons[] = "Contiene la keyword: '{$user_keyword}'";
        }
        
        foreach ($title_keywords as $keyword) {
            if (stripos($text_lower, $keyword) !== false) {
                $reasons[] = "Contiene parola del titolo: '{$keyword}'";
            }
        }
        
        $length = mb_strlen($text, 'UTF-8');
        $reasons[] = "Lunghezza ottimale per titolo: {$length} caratteri";
        
        return $reasons;
    }
    
    /**
     * Estrae il contenuto testuale preservando la formattazione
     */
    private function extract_text_content($block) {
        if (!isset($block['innerHTML']) || empty($block['innerHTML'])) {
            return '';
        }
        
        // Estrai solo il testo per l'analisi, ma mantieni l'HTML originale
        $text = wp_strip_all_tags($block['innerHTML']);
        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    }
    
    /**
     * Trasforma il contenuto in heading preservando la formattazione HTML
     */
    private function transform_to_heading($html_content, $level) {
        // Rimuovi i tag <p> se presenti e sostituisci con heading
        $content = preg_replace('/^<p[^>]*>(.*)<\/p>$/s', '$1', $html_content);
        
        return "<h{$level} class=\"wp-block-heading auto-seo-generated\">{$content}</h{$level}>";
    }
    
    /**
     * Crea i file necessari se non esistono
     */
    private function ensure_assets_exist() {
        $plugin_dir = plugin_dir_path(__FILE__);
        $assets_dir = $plugin_dir . 'assets/';
        
        // Crea la directory assets se non esiste
        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
        }
        
        // Crea editor.js se non esiste
        if (!file_exists($assets_dir . 'editor.js')) {
            $this->create_editor_js($assets_dir);
        }
        
        // Crea editor.css se non esiste
        if (!file_exists($assets_dir . 'editor.css')) {
            $this->create_editor_css($assets_dir);
        }
        
        // Crea frontend.css se non esiste
        if (!file_exists($assets_dir . 'frontend.css')) {
            $this->create_frontend_css($assets_dir);
        }
        
        // Crea admin.css se non esiste
        if (!file_exists($assets_dir . 'admin.css')) {
            $this->create_admin_css($assets_dir);
        }
    }
    
    /**
     * Crea il file JavaScript per l'editor
     */
    private function create_editor_js($assets_dir) {
        $editor_js = '
(function() {
    wp.domReady(function() {
        if (!wp.plugins || !wp.editPost || !wp.components || !wp.blocks || !wp.blockEditor) {
            console.error("Auto SEO Headings: WordPress components not loaded");
            return;
        }
        
        const { registerPlugin } = wp.plugins;
        const { PluginDocumentSettingPanel } = wp.editPost;
        const { TextControl, ToggleControl, Button } = wp.components;
        const { useSelect, useDispatch } = wp.data;
        const { createElement: el, useState } = wp.element;

        const AutoSEOHeadingsPanel = function() {
            const { editPost } = useDispatch("core/editor");
            const [suggestions, setSuggestions] = useState([]);
            const [loading, setLoading] = useState(false);

            const { focusKeyword, enabled } = useSelect(function(select) {
                const meta = select("core/editor").getEditedPostAttribute("meta");
                return {
                    focusKeyword: meta && meta[autoSeoHeadingsData.metaKeyKeyword] ? meta[autoSeoHeadingsData.metaKeyKeyword] : "",
                    enabled: meta && meta[autoSeoHeadingsData.metaKeyEnabled] !== undefined ? meta[autoSeoHeadingsData.metaKeyEnabled] : true
                };
            }, []);

            const getSuggestions = function() {
                setLoading(true);
                
                const blocks = wp.data.select("core/block-editor").getBlocks();
                const postTitle = wp.data.select("core/editor").getEditedPostAttribute("title") || "";
                const focusKeyword = wp.data.select("core/editor").getEditedPostAttribute("meta")[autoSeoHeadingsData.metaKeyKeyword] || "";
                
                console.log("Analisi locale - Titolo:", postTitle, "Keyword:", focusKeyword);
                
                const titleKeywords = extractKeywordsFromTitle(postTitle);
                console.log("Keywords estratte:", titleKeywords);
                
                const newSuggestions = [];
                
                blocks.forEach(function(block) {
                    // Considera sia paragrafi che heading
                    if (block.name === "core/paragraph" || block.name === "core/heading") {
                        const textContent = extractTextFromBlock(block);
                        
                        if (textContent && textContent.length >= 8 && textContent.length <= 80) {
                            const suggestedLevel = determineHeadingLevel(textContent, titleKeywords, focusKeyword);
                            const currentLevel = block.name === "core/heading" ? block.attributes.level : null;
                            
                            newSuggestions.push({
                                clientId: block.clientId,
                                text_content: textContent,
                                suggested_level: suggestedLevel,
                                current_level: currentLevel,
                                block_type: block.name,
                                length: textContent.length
                            });
                        }
                    }
                });
                
                console.log("Suggerimenti generati:", newSuggestions.length);
                setSuggestions(newSuggestions);
                setLoading(false);
            };
            
            const extractKeywordsFromTitle = function(title) {
                if (!title) return [];
                
                const titleLower = title.toLowerCase();
                const words = titleLower.replace(/[^\\w\\s]/g, \' \').split(/\\s+/);
                const stopwords = [\'il\', \'lo\', \'la\', \'i\', \'gli\', \'le\', \'un\', \'uno\', \'una\', \'di\', \'a\', \'da\', \'in\', \'con\', \'su\', \'per\', \'e\', \'o\', \'che\', \'come\', \'quando\', \'dove\', \'se\'];
                
                return words.filter(function(word) {
                    return word.length >= 3 && !stopwords.includes(word);
                });
            };
            
            const extractTextFromBlock = function(block) {
                if (!block.attributes || !block.attributes.content) return "";
                
                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = block.attributes.content;
                return (tempDiv.textContent || tempDiv.innerText || "").trim();
            };
            
            const determineHeadingLevel = function(text, titleKeywords, userKeyword) {
                const textLower = text.toLowerCase();
                
                if (userKeyword && textLower.includes(userKeyword.toLowerCase())) {
                    return 2;
                }
                
                for (let i = 0; i < titleKeywords.length; i++) {
                    if (textLower.includes(titleKeywords[i])) {
                        return 2;
                    }
                }
                
                return 3;
            };

            const transformBlock = function(clientId, headingLevel) {
                console.log("Trasformando blocco con clientId:", clientId, "in H" + headingLevel);
                
                const block = wp.data.select("core/block-editor").getBlock(clientId);
                
                if (!block) {
                    console.error("Blocco non trovato con clientId:", clientId);
                    return;
                }
                
                let newBlock;
                
                if (block.name === "core/paragraph") {
                    newBlock = wp.blocks.createBlock("core/heading", {
                        level: headingLevel,
                        content: block.attributes.content
                    });
                } else if (block.name === "core/heading") {
                    newBlock = wp.blocks.createBlock("core/heading", {
                        level: headingLevel,
                        content: block.attributes.content
                    });
                } else {
                    console.error("Tipo di blocco non supportato:", block.name);
                    return;
                }
                
                wp.data.dispatch("core/block-editor").replaceBlock(clientId, newBlock);
                
                console.log("Trasformazione completata!");
                
                setTimeout(function() {
                    getSuggestions();
                }, 100);
            };

            const convertToParagraph = function(clientId) {
                console.log("Convertendo heading in paragrafo, clientId:", clientId);
                
                const block = wp.data.select("core/block-editor").getBlock(clientId);
                
                if (!block || block.name !== "core/heading") {
                    console.error("Blocco non trovato o non è un heading:", clientId);
                    return;
                }
                
                const newBlock = wp.blocks.createBlock("core/paragraph", {
                    content: block.attributes.content
                });
                
                wp.data.dispatch("core/block-editor").replaceBlock(clientId, newBlock);
                
                console.log("Conversione in paragrafo completata!");
                
                setTimeout(function() {
                    getSuggestions();
                }, 100);
            };

            return el(PluginDocumentSettingPanel, {
                name: "auto-seo-headings-panel",
                title: "Auto SEO Headings",
                className: "auto-seo-headings-panel"
            }, [
                el("div", { style: { marginBottom: "16px" } }, [
                    el(ToggleControl, {
                        label: "Abilita suggerimenti SEO",
                        checked: enabled,
                        onChange: function(value) {
                            editPost({ 
                                meta: { 
                                    [autoSeoHeadingsData.metaKeyEnabled]: value 
                                } 
                            });
                        }
                    })
                ]),
                
                enabled && el("div", {}, [
                    el(TextControl, {
                        label: "Keyword Extra (Opzionale)",
                        value: focusKeyword,
                        onChange: function(value) {
                            editPost({ 
                                meta: { 
                                    [autoSeoHeadingsData.metaKeyKeyword]: value 
                                } 
                            });
                        },
                        placeholder: "Aggiungi keyword per H2..."
                    }),
                    
                    el(Button, {
                        isPrimary: true,
                        isSmall: true,
                        isBusy: loading,
                        onClick: getSuggestions,
                        style: { marginTop: "8px" }
                    }, "Analizza Contenuto"),
                    
                    suggestions.length > 0 && el("div", { style: { marginTop: "16px" } }, [
                        el("h4", { style: { margin: "0 0 12px 0", fontSize: "13px", fontWeight: "600" } }, "Suggerimenti trovati: " + suggestions.length),
                        suggestions.map(function(suggestion, index) {
                            const isH2 = suggestion.suggested_level === 2;
                            const borderColor = isH2 ? "#00a32a" : "#ffb900";
                            const backgroundColor = isH2 ? "#f0f9ff" : "#fffbf0";
                            
                            let statusText = "";
                            if (suggestion.block_type === "core/paragraph") {
                                statusText = "Paragrafo";
                            } else if (suggestion.block_type === "core/heading") {
                                statusText = "H" + suggestion.current_level;
                            }
                            
                            return el("div", { 
                                key: suggestion.clientId,
                                style: { 
                                    border: "2px solid " + borderColor, 
                                    padding: "12px", 
                                    marginBottom: "10px",
                                    borderRadius: "6px",
                                    backgroundColor: backgroundColor
                                }
                            }, [
                                el("div", { style: { display: "flex", justifyContent: "space-between", marginBottom: "8px" } }, [
                                    el("span", { style: { fontSize: "11px", color: "#666", fontWeight: "500" } }, 
                                        "Attuale: " + statusText
                                    ),
                                    el("span", { style: { fontSize: "11px", color: "#666" } }, 
                                        suggestion.length + " caratteri"
                                    )
                                ]),
                                
                                el("p", { style: { margin: "0 0 10px 0", fontWeight: "500", fontSize: "13px", lineHeight: "1.4" } }, 
                                    "\\"" + suggestion.text_content + "\\""
                                ),
                                
                                el("div", { style: { display: "flex", gap: "6px", flexWrap: "wrap" } }, [
                                    suggestion.current_level !== 2 && el(Button, {
                                        isPrimary: suggestion.suggested_level === 2,
                                        isSecondary: suggestion.suggested_level !== 2,
                                        isSmall: true,
                                        onClick: function() {
                                            transformBlock(suggestion.clientId, 2);
                                        }
                                    }, suggestion.block_type === "core/paragraph" ? "Converti in H2" : "Cambia in H2"),
                                    
                                    suggestion.current_level !== 3 && el(Button, {
                                        isPrimary: suggestion.suggested_level === 3,
                                        isSecondary: suggestion.suggested_level !== 3,
                                        isSmall: true,
                                        onClick: function() {
                                            transformBlock(suggestion.clientId, 3);
                                        }
                                    }, suggestion.block_type === "core/paragraph" ? "Converti in H3" : "Cambia in H3"),
                                    
                                    suggestion.block_type === "core/heading" && el(Button, {
                                        isDestructive: true,
                                        isSmall: true,
                                        onClick: function() {
                                            convertToParagraph(suggestion.clientId);
                                        }
                                    }, "Torna a Paragrafo")
                                ])
                            ]);
                        })
                    ])
                ])
            ]);
        };

        registerPlugin("auto-seo-headings", {
            render: AutoSEOHeadingsPanel
        });
    });
})();
';
        file_put_contents($assets_dir . 'editor.js', $editor_js);
    }
    
    /**
     * Crea il CSS per l'editor
     */
    private function create_editor_css($assets_dir) {
        $editor_css = '
.auto-seo-headings-panel .components-panel__body {
    border: none;
}

.auto-seo-suggestion {
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 8px;
    background: #fff;
}

.auto-seo-suggestion.high-confidence {
    border-color: #00a32a;
    background: #f6ffed;
}

.auto-seo-suggestion.medium-confidence {
    border-color: #ffb900;
    background: #fffbf0;
}
';
        file_put_contents($assets_dir . 'editor.css', $editor_css);
    }
    
    /**
     * Crea il CSS per il frontend
     */
    private function create_frontend_css($assets_dir) {
        $frontend_css = '
h2.auto-seo-generated,
h3.auto-seo-generated {
    font-weight: bold;
    line-height: 1.3;
    margin: 1.5em 0 0.5em 0;
}

h2.auto-seo-generated {
    font-size: 1.5em;
}

h3.auto-seo-generated {
    font-size: 1.3em;
}
';
        file_put_contents($assets_dir . 'frontend.css', $frontend_css);
    }
    
    /**
    * Crea il CSS per l'admin
    */
   private function create_admin_css($assets_dir) {
       $admin_css = '
h2.auto-seo-generated,
h3.auto-seo-generated {
   font-weight: bold;
   line-height: 1.3;
   margin: 1.5em 0 0.5em 0;
}

h2.auto-seo-generated {
   font-size: 1.5em;
}

h3.auto-seo-generated {
   font-size: 1.3em;
}
';
       file_put_contents($assets_dir . 'admin.css', $admin_css);
   }
}

// Inizializza il plugin
new AutoSEOHeadingsNonDestructive();

?>
