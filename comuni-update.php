<?php
    $skip_on_error = [];
    $soe_arr = array_values(array_filter($argv, function($v) {return str_starts_with($v, '--skip-on-error=');}));
    if (count($soe_arr) == 1) {
        $skip_on_error = explode(',',explode('=', $soe_arr[0])[1]);
    }
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
    $province_old_filename = $last_cycle[1];
    $province_new = false;
    $last_cycle_d_arr = array_map(function($v){return intval($v);}, explode('-',$last_cycle[2]));
    $last_cycle_d = date_create();
    $last_cycle_d->setDate(...$last_cycle_d_arr);
    $last_cycle_d->setTime(0,0,0,0);

    // AGGIORNA COMUNI ATTIVI
    if (!in_array('--skip-attivi', $argv)) {
        $skip_phase = false;
        $province = file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/province');
        if ($province === false) {
            if (in_array('attivi', $skip_on_error))  {
                $skip_phase = true;
                echo 'SKIPPING "attivi" PHASE DUE TO ERROR GETTING PROVINCE';
            } else {
                echo 'ERROR GETTING PROVINCE';
                exit(1);
            }
        }
        if (!$skip_phase) {
            $province = json_decode($province,true);
            $province_new = array_map(function($p) {
                $p['soppressa'] = false;
                return $p;
            },$province);
            $comuni_attivi = file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/comuni');
            if ($comuni_attivi === false) {
                if (in_array('attivi', $skip_on_error))  {
                    $skip_phase = true;
                    echo 'SKIPPING "attivi" PHASE DUE TO ERROR GETTING COMUNI ATTIVI';
                } else {
                    echo 'ERROR GETTING COMUNI ATTIVI';
                    exit(1);
                }
            }
            if (!$skip_phase) {
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
                            $comuni_old[$k]['provincia'] = array_values(array_filter($province, function ($p) use ($c) {return $p['nome'] == $c['provincia']['nome'];}))[0];
                            if ($c['cap'] && (count($comuni_old[$k]['cap']) == 1 || in_array('--no-preserve-multicap', $argv) )) {
                                $comuni_old[$k]['cap'] = [$c['cap']];
                            }
                            $comuni_old[$k]['prefisso'] = $c['prefisso'];
                            $comuni_old[$k]['email'] = $c['email'];
                            $comuni_old[$k]['pec'] = $c['pec'];
                            $comuni_old[$k]['telefono'] = $c['telefono'];
                            $comuni_old[$k]['fax'] = $c['fax'];
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
        }
    }

    // AGGIORNA COMUNI SOPPRESSI
    if (in_array('--skip-soppressi', $argv)) {
        $soppressi = [];
    } else {
        $skip_phase = false;
        $soppressi = file_get_contents('https://situas.istat.it/ShibO2Module/api/Report/Spool/' . date_create()->format('Y-m-d') . '/128?&pdoctype=JSON' , false, stream_context_create(['http'=> [
            'header'=> "Content-Type: application/json-patch+json\r\n",
            'method' => 'POST',
            'content' => '{"orderFields": [], "orderDirects": [], "pFilterFields": [], "pFilterValues": []}'
        ]]) );
        if ($soppressi === false) {
            if (in_array('soppressi', $skip_on_error))  {
                $skip_phase = true;
                $soppressi = [];
                echo 'SKIPPING "soppressi" PHASE DUE TO ERROR GETTING COMUNI SOPPRESSI';
            } else {
                echo 'ERROR GETTING COMUNI SOPPRESSI';
                exit(1);
            }
        }
        if (!$skip_phase) {
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
                        if (!isset($comuni_old[$k]['verso'])) {
                            $comuni_old[$k]['verso'] = [];
                        }
                    }
                    foreach( $filtered as $cf ) {
                        $comuni_old[$k]['verso'][] = $cf['PRO_COM_T_REL'];
                    }
                }
            }
        }
    }
    
    
    // UPDATE MULTICAP
    if (!in_array('--skip-multicap', $argv)) {
        $skip_phase = false;
        $multicap_page = file_get_contents('https://www.comuni-italiani.it/cap/multicap.html');
        if ($multicap_page === false) {
            if (in_array('multicap', $skip_on_error))  {
                $skip_phase = true;
                echo 'SKIPPING "multicap" PHASE DUE TO ERROR GETTING MULTICAP';
            } else {
                echo 'ERROR GETTING MULTICAP';
                exit(1);
            }
        }
        if (!$skip_phase) {
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
    $end_filename = date_create()->format('YmdHis') . '.json';
    $comuni_filename = 'comuni-all-' . $end_filename;
    $province_filename = $province_old_filename;
    if ($province_new) {
        $province_filename = 'province-all-' . $end_filename;
        foreach ( $comuni_old as $c ) {
            if (isset($c['provincia'])) {
                $found = array_filter($province_new, function ($p) use ($c) {return $p['sigla'] == $c['provincia']['sigla'];});
                if (!count($found)) {
                    $p = $c['provincia'];
                    $p['soppressa'] = true;
                    $province_new[] = $p;
                }
            }
        }
        file_put_contents(__DIR__ . "/bk/$province_filename",json_encode($province_new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    file_put_contents(__DIR__ . "/bk/$comuni_filename", json_encode($comuni_old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents(__DIR__ . '/bk/last-cycle.txt', $comuni_filename . ',' . $province_filename . ',' . $last_cycle_s);
