<?php
$urls = $_GET['urls'] ?: 'http://www.google.com';

$urls = explode( ',', $urls );
$results = array();

$path = __DIR__.'/logs/';

if ( !file_exists( $path ) )
{
    $oldmask = umask(0);
    mkdir( $path, 0777, true );
    umask($oldmask);
}

foreach ( $urls as $i => $url )
{
    // get the headers and response time
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_NOBODY,         true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    $r = curl_exec($ch);
    $info = curl_getinfo($ch);
    $r = split("\n", $r);
    curl_close($ch);

    $file = $path . str_replace( array('http://', 'https://', '/'), array('',''), $url ) . '.txt';

    $h = fopen( $file, 'a+' );

    $history = @fread( $h, filesize($file) );
    $history = json_decode( $history, true ) ?: array();

    $response_time = (double) round( $info['total_time'] * 1000 ); //ms

    // just clean up for the file name
    // log the current timestamp for reference
    $results[$i] = array(
                    'url' => str_replace( array('www.','http://', 'https://'), array('',''), $url ),
                    'response_time' => number_format( $response_time ),
                    'code' => $info['http_code']
    );

    array_push( $history, $results[$i] );

    $history = array_slice( $history, count($history)-2 );

    $results[$i]['previous_response_time'] = $history[count($history)-2]['response_time'];
    $results[$i]['value_change'] = $results[$i]['response_time'] - $results[$i]['previous_response_time'];

    $results[$i]['value_change'] = $results[$i]['value_change'] >= 0 ? '+'.$results[$i]['value_change'] : $results[$i]['value_change'];
    $results[$i]['value_change'] = number_format( $results[$i]['value_change'] );

    $results[$i]['percent_change'] = 100;
    if ( $results[$i]['previous_response_time'] )
    {
        $results[$i]['percent_change'] = round( ($results[$i]['value_change']/$results[$i]['previous_response_time']), 2 );
    }

    $results[$i]['percent_change'] = $results[$i]['percent_change'] >= 0 ? '+'.$results[$i]['percent_change'] : $results[$i]['percent_change'];
    $results[$i]['percent_change'] = number_format( $results[$i]['percent_change'] );

    ftruncate( $h, 0 );
    fwrite( $h, json_encode( $history ) . PHP_EOL );
    fclose( $h );
}

echo json_encode($results);
