<?php
    date_default_timezone_set('Europe/Rome');
    function get_italian_date($date) {
        $date_arr = array_map( function($v) {return intval($v);}, explode('/', $date));
        $d = date_create();
        $d->setDate($date_arr[2], $date_arr[1], $date_arr[0]);
        $d->setTime(0,0,0,0);
        return $d;
    }
    $last_cycle = explode(',', file_get_contents(__DIR__ . '/bk/last-cycle.txt'));
    $comuni_old = json_decode(file_get_contents(__DIR__ . "/bk/$last_cycle[0]"), true);
    
    $last_cycle_d_arr = array_map(function($v){return intval($v);}, explode('-',$last_cycle[1]));
    $last_cycle_d = date_create();
    $last_cycle_d->setDate(...$last_cycle_d_arr);
    $last_cycle_d->setTime(0,0,0,0);

    // AGGIORNA COMUNI ATTIVI
    if (!in_array('--skip-attivi', $argv)) {
        $province = file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/province');
        if ($province === false) {
            echo 'ERROR GETTING PROVINCE';
            exit(1);
        }
        $province = json_decode($province,true);
        $comuni_attivi = file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/comuni');
        if ($comuni_attivi === false) {
            echo 'ERROR GETTING COMUNI ATTIVI';
            exit(1);
        }
        $comuni_attivi = json_decode($comuni_attivi, true);
        $today_utc = date_create();
        $today_utc->setTime(0,0,0,0);
        $today_utc->setTimezone(new DateTimeZone("UTC"));
        foreach($comuni_old as $k => $c) {
            if (isset($c['soppresso']) && $c['soppresso']) {
                continue;
            }
            $found = false;
            foreach($comuni_attivi as $ca) {
                if ($c['codiceCatastale'] == $ca['codiceCatastale']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $comuni_old[$k]['soppresso'] = true;
                $comuni_old[$k]['dataSoppressione'] = $today_utc->format('Y-m-d\TH:i:s.v\Z');
                $comuni_old[$k]['pendingDate'] = true;
            }
        }
        foreach($comuni_attivi as $c) {
            $found = false;
            foreach($comuni_old as $k=>$co) {
                if ($c['codiceCatastale'] == $co['codiceCatastale']) {
                    $found = true;
                    $co[$k]['provinca'] = array_values(array_filter($province, function ($p) use ($c) {return $p['nome'] == $c['provincia']['nome'];}))[0];
                    $co[$k]['cap'] = [$c['cap']];
                    $co[$k]['prefisso'] = $c['prefisso'];
                    $co[$k]['email'] = $c['email'];
                    $co[$k]['pec'] = $c['pec'];
                    $co[$k]['telefono'] = $c['telefono'];
                    $co[$k]['fax'] = $c['fax'];
                    break;
                }
            }
            if (!$found) {
                $comuni_old[] = [
                    'nome' => mb_strtoupper( $c['nome'], 'UTF-8' ),
                    'provincia' => array_values(array_filter($province, function ($p) use ($c) {return $p['nome'] == $c['provincia']['nome'];}))[0],
                    'soppresso' => false,
                    'cap' => [$c['cap']]
                ] + $c;
            }
        }
    }

    // AGGIORNA COMUNI SOPPRESSI
    if (in_array('--skip-soppressi', $argv)) {
        $soppressi = [];
    } else {
        $soppressi = file_get_contents('https://situas.istat.it/ShibO2Module/api/Report/Spool/' . date_create()->format('Y-m-d') . '/128?&pdoctype=JSON' , false, stream_context_create(['http'=> [
            'header'=> "Content-Type: application/json-patch+json\r\n",
            'method' => 'POST',
            'content' => '{"orderFields": [], "orderDirects": [], "pFilterFields": [], "pFilterValues": []}'
        ]]) );
        if ($soppressi === false) {
            echo 'ERROR GETTING COMUNI SOPPRESSI';
            exit(1);
        }
        $soppressi = array_filter(
            json_decode($soppressi, true)['resultset'],
            function($c) use ($last_cycle_d) {return get_italian_date($c['DATA_INIZIO_AMMINISTRATIVA'])->getTimestamp() >= $last_cycle_d->getTimestamp(); }
        );
        foreach($comuni_old as $k => $c) {
            if (isset($c['codice']) && $c['codice'] && (!$c['soppresso'] || ($c['soppresso'] && isset($c['pendingDate']) && $c['pendingDate']))) {
                $filtered = array_values( array_filter($soppressi, function($cs) use ($c) {return $cs['PRO_COM_T'] == $c['codice'];}) );
                if (count($filtered) > 0) {
                    unset($comuni_old[$k]['pendingDate']);
                    $d = get_italian_date($filtered[0]['DATA_INIZIO_AMMINISTRATIVA']);
                    $d->setTimezone(new DateTimeZone("UTC"));
                    $comuni_old[$k]['dataSoppressione'] = $d->format('Y-m-d\TH:i:s.v\Z');
                    $comuni_old[$k]['soppresso'] = true;
                    $comuni_old[$k]['verso'] = $filtered[0]['PRO_COM_T_REL'];
                }
            }
        }
    }
    
    
    // UPDATE MULTICAP
    if (!in_array('--skip-multicap', $argv)) {
        $multicap_page = file_get_contents('https://www.comuni-italiani.it/cap/multicap.html');
        if ($multicap_page === false) {
            echo 'ERROR GETTING MULTICAP';
            exit(1);
        }
        $multicap_doc = new DOMDocument();
        $multicap_doc->loadHTML($multicap_page);
        $multicap_doc = new DOMXPath($multicap_doc);
        $multicap_entries = $multicap_doc->query("//table[contains(@class, 'tabwrap')]/tr");
        $multicap = [];
        foreach($multicap_entries as $i=>$p) {
            if ($i == 0) {continue;}
            $tds = $p->getElementsByTagName('td');
            $codice_comune = preg_replace('/(\.\.)|\//', '', $tds[0]->getElementsByTagName('a')[0]->getAttribute('href'));
            $cap_limits = array_map(function ($cap) {return intval($cap);}, explode('-',$tds[1]->textContent));
            $caps = [];
            for ($i = $cap_limits[0]; $i <= $cap_limits[1]; $i++) {
                $caps[] = $i;
            }
            $caps = array_map(function($cap) {return str_pad($cap, 5, '0', STR_PAD_LEFT);}, $caps);
            $multicap[] = [
                'codice' => $codice_comune,
                'cap' => $caps
            ];
        }
        $done = 0;
        foreach( $comuni_old as $k => $c ) {
            if ($done == count($multicap)) {break;}
            if (isset($c['codice']) && $c['codice'] && !$c['soppresso']) {
                foreach($multicap as $mc) {
                    if ($c['codice'] == $mc['codice']) {
                        $comuni_old[$k]['cap'] = $mc['cap'];
                        $done++;
                        break;
                    }
                }
            }
        }
    }

    $chars = 'ABCDEFGHILMNOPQRSTUVZ';
    usort($comuni_old, function($a, $b) use ($chars) {
        $res = strpos($chars, $a['codiceCatastale'][0]) - strpos($chars, $b['codiceCatastale'][0]);
        if ($res == 0) {
            $res = intval(substr($a['codiceCatastale'],1,3)) - intval(substr($b['codiceCatastale'],1,3));
        }
        return $res;
    });
    $last_cycle_s = $last_cycle_d->format('Y-m-d');
    if (count($soppressi) > 0) {
        $md = max(array_map(function($c) {return get_italian_date($c['DATA_INIZIO_AMMINISTRATIVA'])->getTimestamp();}, $soppressi));
        $d = date_create();
        $d->setTimestamp($md);
        $last_cycle_s = $d->format('Y-m-d');
    }
    $filename = 'comuni-all-' . date_create()->format('YmdHis') . '.json';
    file_put_contents(__DIR__ . "/bk/$filename", json_encode($comuni_old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents(__DIR__ . '/bk/last-cycle.txt', $filename . ',' . $last_cycle_s);