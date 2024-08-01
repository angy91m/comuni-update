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
    $province = json_decode(file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/province'),true);
    $comuni_attivi = json_decode(file_get_contents('https://axqvoqvbfjpaamphztgd.functions.supabase.co/comuni'), true);
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
        foreach($comuni_old as $co) {
            if ($c['codiceCatastale'] == $co['codiceCatastale']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $comuni_old[] = [
                'nome' => mb_strtoupper( $c['nome'], 'UTF-8' ),
                'provincia' => array_values(array_filter($province, function ($p) use ($c) {return $p['nome'] == $c['provincia']['nome'];}))[0],
                'soppresso' => false
            ] + $c;
        }
    }
    $last_cycle_d_arr = array_map(function($v){return intval($v);}, explode('-',$last_cycle[1]));
    $last_cycle_d = date_create();
    $last_cycle_d->setDate(...$last_cycle_d_arr);
    $last_cycle_d->setTime(0,0,0,0);
    $soppressi = array_filter(
        json_decode(file_get_contents('https://situas.istat.it/ShibO2Module/api/Report/Spool/' . date_create()->format('Y-m-d') . '/128?&pdoctype=JSON' , false, stream_context_create(['http'=> [
            'header'=> "Content-Type: application/json-patch+json\r\n",
            'method' => 'POST',
            'content' => '{"orderFields": [], "orderDirects": [], "pFilterFields": [], "pFilterValues": []}'
        ]]) ), true)['resultset'],
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