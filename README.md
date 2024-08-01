# comuni-update
Script bash/php per l'aggiornamento dei comuni italiani su file json.
Utilizza le seguenti risorse:
- [Comuni ITA API](https://comuni-ita.readme.io/) come API per l'aggiornamento dei comuni attivi e delle province
- la lista [Comuni soppressi e non ricostituiti](https://situas.istat.it/web/#/home/in-evidenza?id=128&dateFrom=1861-03-17) disponibile su [https://situas.istat.it](https://situas.istat.it/) per l'aggiornamento dei comuni soppressi
- la pagina (https://www.comuni-italiani.it/cap/multicap.html)[https://www.comuni-italiani.it/cap/multicap.html] per l'aggiornamento dei comuni multiCAP.

`comuni-update.php` legge le informazioni sull'ultimo ciclo da `bk/last-cycle.txt`, aggiorna l'ultima lista dei comuni, la salva in un nuovo file json dentro `bk/` e memorizza le informazioni del ciclo concluso nel file `bk/last-cycle.txt`.

`comuni-update.sh` crea un link simbolico chiamato `comuni.json`, se non è già presente, puntandolo all'ultimo file aggiornato dentro `bk/`, lancia lo script php e, se andato a buon fine, aggiorna il puntamento del link verso l'ultimo file eventualmente aggiornato.

## Opzioni
`--skip-attivi` -> Salta l'aggiornamento dei comuni attivi

`--skip-soppressi` -> Salta l'aggiornamento dei comuni soppressi

`--skip-multicap` -> Salta l'aggiornamento del cap dei comuni multiCAP

`--no-preserve-multicap` -> Aggiorna il CAP dei comuni basandosi sul CAP ricavato da [Comuni ITA API](https://comuni-ita.readme.io/) anche per i comuni multiCAP (non ha effetto se l'aggiornamento dei comuni attivi viene saltato)

`--skip-on-error=nomefaseX,nomefaseY` -> Salta l'aggiornamento per i nomi delle fasi indicati. I nomi possono essere: `attivi`, `soppressi`, `multicap`. Ad esempio, se si vuole continuare l'aggiornamento anche in caso di errore download file in tutte e tre le fasi l'opzione sarà `--skip-on-error=attivi,soppressi,multicap`


Godetevelo!
