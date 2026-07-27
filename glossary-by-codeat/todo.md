# Glossary Plugin — Piano di Lavoro (TDD)

## Setup Tecnico Test

**Runner:** Codeception + WPBrowser (suite `wpunit`)  
**Binary:** `vendor/bin/codecept`  
**Env:** `tests/_envs/.env` (WP_ROOT, DB creds, glossary.test domain)  
**Helper:** `tests/_support/functions.php` (`gl_add_term()`, `gl_get_text()`, `gl_set_option()`, `gl_set_filter_option()`)  

**Comandi:**
```bash
# Singolo test file
vendor/bin/codecept run wpunit Core/SomeTest

# Tutti i test wpunit
vendor/bin/codecept run wpunit

# Tutti i test wpunit nella cartella Core
vendor/bin/codecept run wpunit Core
```

**Location file di test:** `tests/wpunit/Core/`  
**Pattern test esistenti:** ogni classe estende `\Codeception\TestCase\WPTestCase`, usa `gl_get_text($text, $terms, $params)` per ottenere l'output parsato, confronta con `$text_parsed` atteso.

**Helper disponibile (`gl_add_term`):**
```php
$terms[] = gl_add_term( 'TermName', $url, 'Tooltip text' );
$text_output = gl_get_text( $text, $terms, array( 'tooltip' => 'link-tooltip' ) );
$this->assertEquals( $expected, $text_output );
```

**NOTA su gl_add_term:** L'helper non include tutti i campi che produce `Terms_List::enqueue_term()` (manca `hash`, `value`, `sponsored`). Per test che necessitano di campi aggiuntivi, costruire l'array manualmente o estendere l'helper.

---

## Metodologia TDD

Ogni task di fix segue questo flusso:
1. **Fase TEST:** scrivere test che riproducono il bug → eseguire → confermare che FALLISCONO
2. **Fase FIX:** applicare la patch → eseguire i test → confermare che PASSANO
3. **Fase VERIFY:** eseguire tutta la suite wpunit esistente → confermare zero regressioni

I task di solo-test (T6, T7, T8) hanno solo la fase TEST.

---

## Tasks

---

### T2: Fix `strpos` boolean bug
**Priorità:** Alta | **Tipo:** TDD fix | **Dipendenze:** Nessuna

**File da modificare:**
- `frontend/Core/Term_Injector.php` — righe 316, 337

**Bug:**
```php
// Riga 316: se '<glwrap' è a posizione 0, strpos ritorna 0 (falsy) → salta l'elaborazione
if ( \strpos( $this->text, '<glwrap' ) ) {
// Riga 337: stesso problema
if ( \strpos( $this->text, '|GL-' ) ) {
```

#### Fase TEST — creare `tests/wpunit/Core/StrposBooleanTest.php`

Test da scrivere (tutti devono FALLIRE prima del fix):

1. **`GlwrapAtPositionZero`**: testo che inizia con `<glwrap>content</glwrap>` — verificare che l'ignore area venga processata correttamente anche quando il tag è all'inizio della stringa
2. **`GlPlaceholderAtPositionZero`**: testo dove `|GL-0|` appare a posizione 0 — verificare che il reinsert funzioni
3. **`GlwrapNotPresent`**: testo senza `<glwrap` — verificare che non crashi (comportamento invariato)
4. **`GlwrapInMiddle`**: testo con `<glwrap>` a metà — verificare comportamento corretto (caso attualmente funzionante, regression guard)

**NOTA:** I metodi `split_replace_ignore_area__premium_only()` e `split_reinsert_ignore_area__premium_only()` sono premium-only. Per testare senza PRO, si può testare indirettamente via `do_wrap()` oppure riflettere sulla visibilità del metodo. In alternativa, testare il pattern con un metodo helper pubblico che replichi la logica.

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/StrposBooleanTest
# Prima del fix: test 1 e 2 devono FALLIRE
```

#### Fase FIX

Cambiare righe 316 e 337:
```php
// Riga 316
if ( \strpos( $this->text, '<glwrap' ) !== false ) {
// Riga 337
if ( \strpos( $this->text, '|GL-' ) !== false ) {
```

#### Fase VERIFY
```bash
vendor/bin/codecept run wpunit Core/StrposBooleanTest   # tutti PASS
vendor/bin/codecept run wpunit                           # zero regressioni
```

**Rollback:** `git checkout frontend/Core/Term_Injector.php`  
**Risk:** Basso

---

### T3: Fix precedenza operatori in `check_auto_link`
**Priorità:** Alta | **Tipo:** TDD fix | **Dipendenze:** Nessuna

**File da modificare:**
- `frontend/Core/Search_Engine.php` — righe 133-144

**Bug:** PHP valuta `&&` prima di `||`. Per block theme la guard non si attiva mai → glossary inietta nell'header.

#### Fase TEST — creare `tests/wpunit/Core/OperatorPrecedenceTest.php`

**Sfida:** `check_auto_link()` dipende da `did_action()`, `wp_is_block_theme()`, `defined('BRICKS_VERSION')` — difficile da isolare.

**Approccio:** Estrarre la condizione di guard in un metodo separato `should_skip_injection()` (refactor estrattivo) e testarlo direttamente. Oppure testare tramite reflection o mock.

Test da scrivere:

1. **`NonBlockThemeBeforeWpPrintStyles`**: simulare tema non-block + `wp_print_styles` non ancora fatto → l'iniezione DEVE essere saltata (return testo originale)
2. **`NonBlockThemeAfterWpPrintStyles`**: simulare tema non-block + `wp_print_styles` già fatto → l'iniezione DEVE avvenire
3. **`BlockThemeBeforeWpPrintStyles`**: simulare block theme + `wp_print_styles` non fatto → DEVE saltare (questo FALLIRà con il bug attuale)
4. **`BlockThemeAfterWpPrintStyles`**: block theme + `wp_print_styles` fatto → DEVE iniettare
5. **`BricksThemeBeforeBricksBody`**: Bricks attivo + `bricks_body` non fatto → DEVE saltare
6. **`BricksThemeAfterBricksBody`**: Bricks attivo + `bricks_body` fatto → DEVE iniettare

**Mock strategy:** Usare il pattern dei test esistenti — creare un post, andare alla URL con `$this->go_to()`, e verificare l'output di `apply_filters('the_content', $text)`. Per simulare block theme, definire `wp_is_block_theme` via `stubs` o monkey patching.

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/OperatorPrecedenceTest
# Prima del fix: test 3 deve FALLIRE (block theme guard non funziona)
```

#### Fase FIX

Riscrivere righe 133-144 con parentesi esplicite:
```php
if (
    (
        (
            !function_exists( 'wp_is_block_theme' )
            || !wp_is_block_theme()
        )
        && !did_action( 'wp_print_styles' )
    )
    || (
        defined( 'BRICKS_VERSION' )
        && !did_action( 'bricks_body' )
    )
) {
    return $text;
}
```

#### Fase VERIFY
```bash
vendor/bin/codecept run wpunit Core/OperatorPrecedenceTest
vendor/bin/codecept run wpunit
```

**Rollback:** `git checkout frontend/Core/Search_Engine.php`  
**Risk:** Basso

---

### T4: Fix encoding `gl_get_len()`
**Priorità:** Media | **Tipo:** TDD fix | **Dipendenze:** Nessuna

**File da modificare:**
- `functions/functions.php` — riga 423

**Bug:** `mb_strlen($s, 'latin1')` — 'latin1' non è alias standard PHP.

#### Fase TEST — creare `tests/wpunit/Core/EncodingLengthTest.php`

Test da scrivere:

1. **`AsciiStringLength`**: verificare che `gl_get_len('hello')` === `strlen('hello')` === 5
2. **`MultibyteUtf8Length`**: `gl_get_len('Über')` deve ritornare 5 (byte length: Ü = 2 bytes + b,e,r = 3)
3. **`CyrillicLength`**: `gl_get_len('Москва')` deve ritornare 12 (6 char × 2 byte UTF-8)
4. **`HebrewWithAsciiLength`**: `gl_get_len('סטארט-אפ')` deve ritornare la byte length corretta (15 byte: 6 char ebraici × 2 + trattino ASCII × 1 + פ × 2 = ma verificare il valore esatto)
5. **`EmojiLength`**: `gl_get_len('🎉')` deve ritornare 4 (UTF-8 byte length per emoji a 4 byte)
6. **`EmptyStringLength`**: `gl_get_len('')` === 0
7. **`ChineseLength`**: `gl_get_len('世界')` === 6 (2 char × 3 byte UTF-8)
8. **`MixedContentLength`**: stringa con ASCII + multi-byte misto, verificare byte length totale
9. **`ConsistencyWithSubstrReplace`**: verificare che la lunghezza ritornata da `gl_get_len()` sia compatibile con gli offset di `preg_match_all` con `PREG_OFFSET_CAPTURE` — testare che `substr_replace` usi la lunghezza corretta

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/EncodingLengthTest
# I test dovrebbero PASSARE anche prima del fix (perché 'latin1' funziona come fallback)
# ma il test documenta il comportamento corretto e protegge da regressioni future
```

#### Fase FIX

Cambiare riga 423:
```php
function gl_get_len( string $stringtomatch ) {
    return strlen( $stringtomatch );
}
```

`strlen()` ritorna sempre la byte length, è più esplicito e non dipende da mbstring.

#### Fase VERIFY
```bash
vendor/bin/codecept run wpunit Core/EncodingLengthTest
vendor/bin/codecept run wpunit Core/ContentNoLatinTest   # test critici per multibyte
vendor/bin/codecept run wpunit
```

**Rollback:** `git checkout functions/functions.php`  
**Risk:** Basso

---

### T5: Fix XSS potenziale nell'excerpt del tooltip
**Priorità:** Media | **Tipo:** TDD fix | **Dipendenze:** Nessuna

**File da modificare:**
- `frontend/Core/Generate_Excerpt.php` — metodo `get()` / `elaborate_the_excerpt()`

**Bug:** L'excerpt del termine glossary finisce dentro HTML del tooltip senza escaping. `strip_tags($excerpt, '<br>')` non rimuove attributi pericolosi o character entities.

#### Fase TEST — creare `tests/wpunit/Core/XssExcerptTest.php`

Test da scrivere (devono FALLIRE prima del fix):

1. **`ScriptTagInExcerpt`**: excerpt con `<script>alert(1)</script>` — verificare che non appaia nell'output del tooltip
2. **`OnclickAttributeInExcerpt`**: excerpt con `<br onclick="alert(1)">` — verificare che l'attributo venga rimosso
3. **`DoubleQuoteBreakout`**: excerpt con `" onload="alert(1)` — verificare che non rompa l'HTML del tooltip span
4. **`SingleQuoteBreakout`**: excerpt con `' onload='alert(1)` — stesso check
5. **`HtmlEntityInjection`**: excerpt con `&quot; onload=&quot;alert(1)` — verificare che non venga interpretato
6. **`SvgOnload`**: excerpt con `<svg onload=alert(1)>` — verificare rimozione
7. **`ImgOnerror`**: excerpt con `<img src=x onerror=alert(1)>` — verificare rimozione
8. **`NormalHtmlPreserved`**: excerpt con `<br>` legittimo — verificare che venga preservato (regression guard)
9. **`NormalTextPreserved`**: excerpt con testo normale e caratteri unicode — verificare integrità (regression guard)

**Pattern:** Usare `gl_get_excerpt($text)` dall'helper, verificare che l'output non contenga payload XSS.

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/XssExcerptTest
# Prima del fix: test 1-7 devono FALLIRE (XSS non filtrato)
```

#### Fase FIX

In `Generate_Excerpt::get()`, dopo `strip_tags`, applicare `wp_kses_post()` sul risultato prima di inserirlo nel tooltip HTML. Questo permette `<br>` ma rimuove script, attributi pericolosi (onload, onclick, onerror), e altri vettori XSS.

In alternativa, se `wp_kses_post` è troppo aggressivo, usare una custom kses allowlist:
```php
$allowed = array(
    'br' => array(),
);
$excerpt = wp_kses( $excerpt, $allowed );
```

#### Fase VERIFY
```bash
vendor/bin/codecept run wpunit Core/XssExcerptTest
vendor/bin/codecept run wpunit Core/TrunkTextTest   # regression check sull'excerpt
vendor/bin/codecept run wpunit
```

**Rollback:** `git checkout frontend/Core/Generate_Excerpt.php`  
**Risk:** Medio — l'escaping può alterare l'output visivo dei tooltip esistenti

---

### T6: Test suite per `replace_with_utf_8()` — matematica posizioni
**Priorità:** Alta | **Tipo:** Solo test | **Dipendenze:** Nessuna (meglio dopo T2)

**File da creare:**
- `tests/wpunit/Core/ReplacePositionsTest.php`

**Scopo:** Documentare e verificare il comportamento di calcolo degli offset per iniezione multipla. Nessuna modifica al codice.

**Casi da testare:**

1. **`SingleTermAtPositionZero`**: termine all'inizio della stringa ("philosophy is great")
2. **`SingleTermAtEnd`**: termine alla fine ("this is philosophy")
3. **`SingleTermInMiddle`**: termine a metà ("this philosophy is great")
4. **`AdjacentTermsNoSpace`**: due termini senza spazio ("term1term2" dove entrambi sono glossary terms)
5. **`AdjacentTermsWithSpace`**: due termini separati da singolo spazio
6. **`OverlappingTerms`**: "amet" e "sit amet" nella stessa stringa — verificare quale vince
7. **`ThreeIdenticalTerms`**: stesso termine 3 volte ("philosophy philosophy philosophy")
8. **`FiveIdenticalTerms`**: stesso termine 5 volte — stress test offset
9. **`CyrillicMultipleTerms`**: multipli termini cirillici
10. **`GreekMultipleTerms`**: multipli termini greci
11. **`ArabicMultipleTerms`**: multipli termini arabi
12. **`HebrewWithAsciiMix`**: termine ebraico + trattino ASCII (es: "סטארט-אפ") + altro termine ebraico dopo
13. **`CaseMixedSameTerm`**: "Philosophy PHILOSOPHY philosophy" — stesso termine, case diverse
14. **`EmptyString`**: stringa vuota con termini — deve ritornare vuoto
15. **`SingleCharTerm`**: termine di 1 carattere
16. **`LongOffsetRecalculation`**: 3+ termini lunghi che richiedono ricalcolo offset dopo ogni iniezione
17. **`FirstOccurrenceOnly`**: con `first_occurrence` impostato — solo prima occorrenza
18. **`HtmlBetweenTerms`**: `<p>term1</p><div>term2</div>` — HTML tra i termini
19. **`TermInHtmlAttribute`**: `alt="philosophy"` — verificare che NON venga sostituito dentro l'attributo
20. **`TermsAtBoundaries`**: termine subito prima di `</p>` e subito dopo `<p>`

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/ReplacePositionsTest
```

**Risk:** Nessuno — solo test

---

### T7: Test suite per CJK senza flag `u`
**Priorità:** Alta | **Tipo:** Solo test | **Dipendenze:** Nessuna

**File da creare:**
- `tests/wpunit/Core/CJKRegexTest.php`

**Scopo:** Verificare il comportamento della regex quando il flag `u` viene rimosso per termini CJK. Nessuna modifica al codice.

**Casi da testare:**

1. **`ChineseExactMatch`**: termine cinese in testo cinese
2. **`ChineseWithPunctuation`**: termine seguito da punteggiatura cinese (。、！？)
3. **`ChineseMultipleOccurrences`**: stesso termine cinese più volte
4. **`ChineseWithLatinMix`**: "Hello 世界 World" — termine cinese in contesto latino
5. **`JapaneseHiraganaTerm`**: termine Hiragana in testo giapponese
6. **`JapaneseKatakanaTerm`**: termine Katakana
7. **`JapaneseKanjiTerm`**: termine Kanji
8. **`KoreanHangulTerm`**: termine coreano
9. **`ChineseSubstringOfWord`**: termine CJK che è substring di un'altra parola CJK
10. **`ChineseFollowedByLatinPunctuation`**: "世界." — punteggiatura latina dopo CJK
11. **`ChineseAtStart`**: termine cinese all'inizio del testo
12. **`ChineseAtEnd`**: termine cinese alla fine del testo
13. **`ChineseNoSpaces`**: testo cinese senza spazi (normale in cinese)
14. **`AdjacentCJKTerms`**: due termini CJK adiacenti senza separatori
15. **`CJKInsideHtmlTag`**: `<p>世界</p>` — termine dentro tag HTML
16. **`CJKInsideHtmlAttribute`**: `title="世界"` — termine in attributo
17. **`CJKMixedWithAscii`**: "AI世界" — termine misto
18. **`CJKLongTerm`**: termine CJK con 10+ caratteri
19. **`CJKPlusLatinTermSameText`**: un termine cinese e uno latino nello stesso testo
20. **`ChineseExistingTestRegression`**: rifare il test `Chinese` esistente in ContentNoLatinTest per confirmare consistenza

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/CJKRegexTest
```

**Risk:** Nessuno — solo test

---

### T8: Test suite per HTML injection edge cases
**Priorità:** Alta | **Tipo:** Solo test | **Dipendenze:** Nessuna (mielglio prima di T1)

**File da creare:**
- `tests/wpunit/Core/HtmlEdgeCasesTest.php`

**Scopo:** Stressare l'iniezione con HTML complesso e conflitti. Documenta i bug noti che T1 dovrà risolvere.

**Casi da testare:**

1. **`TermInsideAnchorWithNestedSpan`**: `<a href="...">click <span>here for philosophy</span></a>` — NON deve iniettare dentro l'anchor
2. **`TermInsideAnchorWithNestedStrong`**: `<a href="...">text <strong>philosophy</strong> text</a>` — NON deve iniettare
3. **`TermInsideAnchorWithNestedEm`**: `<a href="...">text <em>philosophy</em></a>` — NON deve iniettare
4. **`TermInAltAttribute`**: `<img alt="philosophy is great" src="...">` — NON deve iniettare nell'attributo
5. **`TermInTitleAttribute`**: `<a href="..." title="philosophy link">text</a>` — NON deve iniettare
6. **`TermInDataAttribute`**: `<div data-term="philosophy">text</div>` — NON deve iniettare nell'attributo
7. **`TermInInputValue`**: `<input value="philosophy">` — NON deve iniettare
8. **`TermInTextarea`**: `<textarea>philosophy</textarea>` — NON deve iniettare
9. **`TermInPreCode`**: `<pre><code>philosophy code</code></pre>` — NON deve iniettare
10. **`TermInScript`**: `<script>var x = "philosophy";</script>` — NON deve iniettare
11. **`TermInStyle`**: `<style>.philosophy { color: red; }</style>` — NON deve iniettare
12. **`NestedTerms`**: termine A contiene termine B ("amet" dentro "sit amet") — verificare comportamento
13. **`TermWithDot`**: termine con `.` nel nome (es: "v1.0")
14. **`TermWithPlus`**: termine con `+` (es: "C++")
15. **`TermWithParentheses`**: termine con `(` e `)` (es: "function()")
16. **`TermWithBracket`**: termine con `[` e `]`
17. **`TermWithDollar`**: termine con `$`
18. **`TermWithCaret`**: termine con `^`
19. **`TermWithAsterisk`**: termine con `*`
20. **`TermWithQuestionMark`**: termine con `?`
21. **`HtmlEntityInText`**: testo con `&amp;` vicino al termine
22. **`NbspNearTerm`**: testo con `&nbsp;` vicino al termine
23. **`MalformedHtml`**: `<p>philosophy<p>more text` — tag non chiuso
24. **`SelfClosingTagNearTerm`**: `<br />philosophy` — self-closing subito prima
25. **`GutenbergCommentBlock`**: `<!-- wp:paragraph --><p>philosophy</p><!-- /wp:paragraph -->`
26. **`TermAfterClosingTag`**: `</div>philosophy` — subito dopo tag di chiusura
27. **`TermBeforeOpeningTag`**: `philosophy<div>` — subito prima di tag di apertura
28. **`VeryLongContent`**: >10000 caratteri con 10+ termini — verificare che tutti vengano iniettati correttamente
29. **`TermEqualsHtmlTag`**: termine uguale a "span", "div", "a" — verificare che non rompa l'HTML
30. **`MultipleQuotesInTerm`**: termine con virgolette (`"quoted term"`)
31. **`DeeplyNestedHtml`**: 5+ livelli di annidamento con termine nel livello più profondo
32. **`MixedBlockAndInline`**: termine tra tag block e inline misti

**Comando:**
```bash
vendor/bin/codecept run wpunit Core/HtmlEdgeCasesTest
# Molti test FALLIRANNO con il codice attuale — questo documenta i bug noti
```

**Risk:** Nessuno — solo test

---

### T1: Fix regex lookahead per tag HTML annidati
**Priorità:** Alta | **Tipo:** TDD fix | **Dipendenze:** T8 (test suite), T2 (strpos fix)

**File da modificare:**
- `frontend/Core/Terms_List.php` — metodo `search_string()` (riga 109)

**Bug:**
Il negative lookahead `(?![^<]*(\/>|<h|<\/button|...))` fallisce quando c'è un `<` tra il match e il tag di chiusura.

#### Fase TEST

Usare i test di T8 (`HtmlEdgeCasesTest.php`) come suite di validazione. I test 1-11 (termini dentro anchor/pre/code/script/style/attributi) devono passare dopo il fix.

Eseguire prima del fix per confermare quali falliscono:
```bash
vendor/bin/codecept run wpunit Core/HtmlEdgeCasesTest
# Documentare quali test passano e quali falliscono
```

#### Fase FIX

Migliorare il negative lookahead in `search_string()`. Approccio: cambiare `[^<]*` con un pattern che attraversa tag annidati senza consumare i tag di chiusura target.

Opzione A — pattern recursive-ish:
```php
// Sostituire [^<]* con (?:[^<]|<(?!\/?(?:a|pre|code|h[1-6]|button|figcaption)\b))*
```

Opzione B — semplificare usando solo lookbehind + word boundary:
```php
// Rimuovere il lookahead HTML e affidarsi a \b boundaries + negative lookbehind migliorato
```

Opzione C — ibrido: mantenere il lookahead ma migliorare il consuming:
```php
'/(?<![\w\—\-\.\/]|=")(' . $caseinsensitive . ')' . $symbols .
'(?!(?:[^<]|<(?!\/?(?:a|pre|code|h[1-6]|button|figcaption)\b))*(?:\/>|<\/(?:a|pre|code|h[1-6]|button|figcaption)|<h))/' . $unicode
```

**Importante:** Mantenere il filtro `glossary_regex` applicabile. Qualsiasi cambio alla regex deve passare attraverso lo stesso filter hook.

#### Fase VERIFY
```bash
vendor/bin/codecept run wpunit Core/HtmlEdgeCasesTest   # test 1-11 devono passare dopo il fix
vendor/bin/codecept run wpunit Core/ReplacePositionsTest # regression
vendor/bin/codecept run wpunit Core/CJKRegexTest         # regression CJK
vendor/bin/codecept run wpunit                           # FULL regression
```

**Rollback:** `git checkout frontend/Core/Terms_List.php`  
**Risk:** Alto — la regex è il cuore del plugin, cambiamenti possono avere effetti a cascata su tutte le lingue

---

## Dispatch con Subagenti

### Batch 1 — Test-first fix tasks (paralleli, max 4)

| Subagent | Task | Scope | Files |
|----------|------|-------|-------|
| fixer #1 | T2 | Test + fix `strpos` boolean | `tests/wpunit/Core/StrposBooleanTest.php`, `frontend/Core/Term_Injector.php` |
| fixer #2 | T3 | Test + fix operator precedence | `tests/wpunit/Core/OperatorPrecedenceTest.php`, `frontend/Core/Search_Engine.php` |
| fixer #3 | T4 | Test + fix encoding | `tests/wpunit/Core/EncodingLengthTest.php`, `functions/functions.php` |
| fixer #4 | T5 | Test + fix XSS excerpt | `tests/wpunit/Core/XssExcerptTest.php`, `frontend/Core/Generate_Excerpt.php` |

**Nessun conflitto di scrittura** — ogni subagent tocca file diversi.

### Batch 2 — Pure test suites (paralleli, max 3)

Dopo che Batch 1 è completo:

| Subagent | Task | Scope | Files |
|----------|------|-------|-------|
| fixer #1 | T6 | Test suite replace_with_utf_8 | `tests/wpunit/Core/ReplacePositionsTest.php` |
| fixer #2 | T7 | Test suite CJK | `tests/wpunit/Core/CJKRegexTest.php` |
| fixer #3 | T8 | Test suite HTML edge cases | `tests/wpunit/Core/HtmlEdgeCasesTest.php` |

### Batch 3 — Regex fix (singolo)

Dopo che Batch 2 è completo:

| Subagent | Task | Scope | Files |
|----------|------|-------|-------|
| fixer #1 | T1 | Fix regex lookahead | `frontend/Core/Terms_List.php` |

Validazione: usare tutti i test di T8 + regression completa.

---

## Template Briefing Subagent

Ogni subagent riceve:
1. **Role:** "Sei un sviluppatore PHP senior esperto in WordPress"
2. **Context:** descrizione del bug, file coinvolti, righe
3. **Deliverable:** file di test + file modificato
4. **Metodologia:** TDD — scrivere test prima, confermare fallimento, poi fixare, confermare successo
5. **Constraints:** non modificare file fuori scope, mantenere stile codice esistente (tab indentation, PHP 7.4 compat), seguire pattern dei test esistenti in `tests/wpunit/Core/`
6. **Success criteria:** `vendor/bin/codecept run wpunit Core/NomeTest` passa + `vendor/bin/codecept run wpunit` zero regressioni
7. **Helper reference:** `tests/_support/functions.php` per `gl_add_term()`, `gl_get_text()`, `gl_set_option()`, `gl_set_filter_option()`
8. **Test env:** MySQL deve essere running (`tests/custom.sh`), env in `tests/_envs/.env`
