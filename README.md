# Auto-SEO-Headings
Un plugin WordPress intelligente che suggerisce trasformazioni di paragrafi in titoli H2/H3 per migliorare la SEO, senza modifiche automatiche distruttive.

# 🚀 Caratteristiche Principali

- Analisi Intelligente: Rileva automaticamente paragrafi candidati per trasformazione in titoli
- Non Distruttivo: Nessuna modifica automatica, solo suggerimenti manuali
- Ri-trasformazione: Cambia liberamente tra H2, H3 e paragrafo
- Keyword Smart: Utilizza parole del titolo + keyword personalizzata opzionale
- Interfaccia Gutenberg: Pannello integrato nell'editor di blocchi
- Zero Configurazione: Installa e funziona immediatamente

# 📸 Screenshot
Il plugin aggiunge un pannello "Auto SEO Headings" nella sidebar dell'editor Gutenberg che mostra:

- Toggle per abilitare/disabilitare i suggerimenti
- Campo opzionale per keyword extra
- Lista dei suggerimenti con anteprima del testo
- Pulsanti per convertire in H2/H3 o tornare a paragrafo

<img width="1728" height="682" alt="Schermata del 2025-08-03 03-06-51" src="https://github.com/user-attachments/assets/c4f5844b-6a12-4f74-ae11-760078f9e362" />
<img width="1728" height="682" alt="Schermata del 2025-08-03 03-06-57" src="https://github.com/user-attachments/assets/9ee89a59-b2fe-4792-9c85-0c1b22977e98" />


# 🛠 Installazione

- Scarica il file auto-seo-headings.php
- Caricalo nella cartella /wp-content/plugins/auto-seo-headings/
- Attiva il plugin dal pannello amministrativo WordPress
- Il plugin creerà automaticamente tutti i file CSS/JS necessari

# 📋 Requisiti

- WordPress 5.0 o superiore
- PHP 8.0 o superiore
- Editor Gutenberg attivo

# 🎮 Come Usare

- Apri un post/pagina nell'editor Gutenberg
- Scrivi il contenuto normalmente con paragrafi
- Apri il pannello "Auto SEO Headings" nella sidebar destra
- Aggiungi keyword (opzionale) per dare priorità H2
- Clicca "Analizza Contenuto" per ottenere suggerimenti
- Converti i paragrafi usando i pulsanti H2/H3
- Cambia idea liberamente tra H2, H3 e paragrafo

# 🧠 Logica di Analisi

Il plugin analizza i paragrafi e suggerisce:
* H2 (priorità alta) se contiene:
  - Keyword personalizzata inserita dall'utente
  - Parole chiave estratte dal titolo del post

* H3 (priorità normale) per:
  - Paragrafi di lunghezza ottimale (8-80 caratteri)
  - Testo che sembra un titolo ma senza keyword specifiche

* Criteri di esclusione:
  - Paragrafi troppo corti (< 8 caratteri)
  - Paragrafi troppo lunghi (> 80 caratteri)
  - Contenuto già trasformato in heading

# 🎨 Interfaccia Utente

**> Pannello di Controllo**
  - Toggle: Abilita/disabilita suggerimenti per il post corrente
  - Campo Keyword: Inserisci parole chiave per priorità H2
  - Pulsante Analizza: Rilancia l'analisi del contenuto

**> Ogni suggerimento mostra:**
  - Anteprima testo con virgolette
  - Stato corrente: "Paragrafo" o "H2/H3"
  - Lunghezza: Numero di caratteri
  - Pulsanti azione: Dinamici in base allo stato

**> Pulsanti Intelligenti**
  - "Converti in H2/H3": Per paragrafi non ancora trasformati
  - "Cambia in H2/H3": Per heading esistenti che vuoi modificare
  - "Torna a Paragrafo" (rosso): Per riconvertire heading in paragrafo

# 🔄 Funzionalità Avanzate
  - Ri-trasformazione Illimitata
  - Puoi cambiare idea tutte le volte che vuoi:

Paragrafo → H2 → H3 → Paragrafo
  - La lista si aggiorna automaticamente dopo ogni trasformazione
  - Nessun errore di clientId non trovato

# Analisi Locale

  - Zero AJAX: Analisi istantanea senza chiamate server
  - Prestazioni elevate: Usa direttamente l'API Gutenberg
  - Aggiornamento real-time: La lista si sincronizza con l'editor

# Stopwords Italiane
Lista completa di stopwords per estrarre keywords significative:
>  - il, lo, la, di, a, da, in, con, su, per, tra, fra, e, o, che, come, quando, dove, se...

# 🔧 Configurazione

**> Meta Fields**
Il plugin registra automaticamente:
* _auto_headings_focus_keyword: Keyword personalizzata
* _auto_headings_enabled: Stato abilitazione per post

**> Hook WordPress**
* init: Inizializzazione plugin
* enqueue_block_editor_assets: Carica assets Gutenberg
* wp_enqueue_scripts: CSS frontend
* admin_enqueue_scripts: CSS admin

# 💡 Tips & Tricks

* Ottimizzazione SEO
  - Usa keyword nel titolo: Il plugin le rileva automaticamente
  - Aggiungi keyword extra: Per dare priorità H2 a contenuti specifici
  - Mantieni equilibrio: Non trasformare tutto in H2, usa anche H3
  - Rivedi manualmente: I suggerimenti sono intelligenti ma non perfetti

* Workflow Consigliato
  - Scrivi tutto il contenuto normalmente
  - Aggiungi keyword extra se hai termini target specifici
  - Analizza e applica i suggerimenti
  - Rivedi la struttura finale per l'equilibrio H2/H3
 
# 📄 Licenza
GPL v2 or later - Usa, modifica e distribuisci liberamente!

# 👨‍💻 Autore
Flavius Florin Harabor [🌐 2088.it](https://2088.it/)

# 💰 Donazioni
- **[Ko-Fi](https://ko-fi.com/insidetelegramproject)**

Se consideri che questo progetto ti è tornato utile per il tuo lavoro, non esitare a farmi una piccola donazione.


# 📫 Contatti
- [Telegram](https://t.me/ErBoss88)
- [Instagram](https://instagram.com/flaviusharabor/)
- [Twitter](https://twitter.com/FlaviusHarabor)
- [LinkedIn](https://www.linkedin.com/in/flaviusflorinharabor/)
- [YouTube](http://www.youtube.com/c/FlaviusFlorinHarabor)
