<?php
/* db.php opens the database connection */
include_once( '../include/db.php' );

/*
 * json functions were added with PHP 5.2. If you get this
 * error message, you need to install a JSON library separately.
 */
if( !function_exists( 'json_decode' ) ) {
    server_error( 'JSON functions are not supported by this PHP installation' );
}

if (isset($_POST['data'])) {
	$data = $_POST['data'];
}
else {
	$data = json_decode(file_get_contents('php://input'), true);
}

if(!isset($data['sessionId'])) {
	die( 'Session identifier missing' );
}

$session = $data['sessionId'];
$story = $_GET['story'];

// check if the session data is already in the database, save if not
$query = $db->prepare(
	"SELECT COUNT(*) as count FROM {$dbSettings[ 'prefix' ]}stories 
		WHERE session = ?"
);

$query->execute(array($session));
$count = $query->fetchAll();

if( $count[ '0' ][ 'count' ] == 0 ) { 
	$insert = $db->prepare(
		"INSERT INTO {$dbSettings[ 'prefix' ]}stories 
			SET session = ?,
			story = ?,
			version = ?,
			interpreter = ?,
			browser = ?,
			started = ?"
	);
	
	$interpreter = '';
	$browser = '';
	$storyVersion = '';
	
	$insert->execute( 
		array(
			$session,
			$story,
			$storyVersion,
			$interpreter,
			$browser,
			date( 'Y-m-d H:i:s' )
		)
	) or server_error( 'Error saving startup data: '.print_r( $insert->errorInfo(), true ) );
}

$timestamp = date( 'Y-m-d H:i:s', round($data['timestamp'] / 1000 ) );

$insert = $db->prepare( 
	"INSERT INTO {$dbSettings[ 'prefix' ]}transcripts 
	SET session = ?,
		input = ?,
		output = ?,
		window = ?,
		styles = ?,
		inputcount = ?,
		outputcount = ?,
		timestamp = ?"
);

$insert->execute(
	array(
		$session,
		$data[ 'input' ],
		$data[ 'output' ],
		0,
		'',
		0,
		0,
		$timestamp
	)
) or server_error( 'Error saving log data: '.print_r( $insert->errorInfo(), true ) );


// Update story information. The "ended" counter is updated as transcript
// pieces are saved.
$storyupdate = $db->prepare(
	"UPDATE {$dbSettings[ 'prefix' ]}stories 
	SET ended = IF( ended < :timestamp, :timestamp, ended ),
		inputcount = IF( inputcount < :count, :count, inputcount )
	WHERE session = :session"
);

$storyupdate->execute(
	array(
		':timestamp'	=> $timestamp,
		':count'		=> 0,
		':session'		=> $session
	)
) or server_error( 'Error updating story data: '.print_r( $storyupdate->errorInfo(), true ) );

die( 'OK' );
