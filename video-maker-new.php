<?php

if ($dir = $argv[1] ?? 'D:\tutorials\09 Roundup') {
	$options = '';
	$files = files($dir);
	$count = 0;

	foreach ( $files as $name => $path) {
		echo "$path\n";
		$options .= sprintf('<option value="%s">%s (%d of %d)</option>', strtr(preg_replace("/\Q$dir\\\E/", '', $path), ['\\' => '/']), 
						substr(pathinfo($name, PATHINFO_FILENAME), 0, 80), ++$count, count($files));
	}

	for ($i = 1; $i <= 6; $i += 0.1) {
		$speeds .= sprintf('<option value="%.02f">%.02f</option>', $i, $i);
	}


	$html =<<<EOF
	<html>
		<head>
			<style>
				html, body, input, select {
					font-family: Tahoma;
					font-size: 12px;
				}

				select {
					height: 22px;
				}

				input[type=number] {
					border: 0;
					width: 15px;
				}

				input[type=number]::-webkit-inner-spin-button, 
				input[type=number]::-webkit-outer-spin-button {  
				   opacity: 1;
				   height: 25px;
				}
			</style>
		</head>
		<body>
			<table align="center">
				<tr>
					<td><button onclick="delta(-1)">&lt;</button></td>
					<td>
						<select id='sel' onchange='delta(0)'>
							$options
						</select>
					</td>
					<td>
						<select id='speed' onchange='vidSpeed(this.value)'>
							$speeds
						</select>
					</td>
					<td style="margin-left:-5px;">
						<input type="number" step="0.1" id="spinner" oninput="vidSpeed(this.value)">
					</td>
					<td><button onclick="delta(1)">&gt;</button></td>
					<td id="prog"></td>
				</tr>
			</table>

			
			<hr />

			<center>
				<video id="vid" preload="auto" controls="true" style="max-width:98%;height:93vh"><source src="" type="video/mp4" tabindex="0"></source></video>	
			</center>

			<script>
				var vid, sel, body, prog, speed, spinner;

				function delta(by) {
					go(sel.selectedIndex + by);
				}

				function go(index) {					
					var opt = sel.options[index];
					var file = opt.value;
					var rate = vid.playbackRate;
					
					vid.src = file;
					document.title = opt.text;
					sel.selectedIndex = index;
					
					window.history.pushState(null, index, '#' + index);
					vid.play();
					vidSpeed(rate);
				}

				function init() {
					vid = document.getElementById('vid');
					sel = document.getElementById('sel');
					prog = document.getElementById('prog');
					speed = document.getElementById('speed');
					spinner = document.getElementById('spinner');
					body = document.body;

					body.addEventListener('mousewheel', function(evt) { vid.volume = Math.max(0, Math.min(1, vid.volume - (evt.deltaY / 3000))); });

					vid.addEventListener('click', toggle);
					vid.addEventListener('dblclick', toggleFullScreen);
					vid.addEventListener('ended', function() { delta(1); });
					vid.addEventListener('ratechange', function(event) { setSpeed(); });
					vid.addEventListener('timeupdate', function() { prog.innerText = Math.round(this.currentTime / (this.duration || 1) * 100) + '%'; });

					document.body.addEventListener('keydown', function(evt) { 
						var call = function(fn, arg) { evt.preventDefault(); fn(arg); }; 
						if (evt.keyCode == 32) call(toggle); 

						if (evt.keyCode == 37) evt.shiftKey ? call(delta, -1) : call(skip, -5); 						
						if (evt.keyCode == 39) evt.shiftKey ? call(delta, 1) : call(skip, 5); 						

						if (evt.keyCode == 38) call(vol, 0.01); 
						if (evt.keyCode == 40) call(vol, -0.01);
					});
					
					go(window.location.hash.substr(1) || 0);
					vidSpeed(4);
					
					document.body.style.overflow = 'hidden';					
				}

				function skip(by) {
					vid.currentTime = Math.max(0, Math.min(vid.duration, vid.currentTime + by));
				}

				function vidSpeed(val) {
					vid.playbackRate = val;
				}

				function setSpeed() {
					var val = vid.playbackRate.toFixed(2);
					var els = document.querySelector('#speed option[value="'+val+'"]');
					
					if (els) {
						els.selected = true;
					}
					
					spinner.value = val;
				}

				function vol(by) {
					prog.innerText = vid.volume = Math.max(Math.min(vid.volume + by, 1), 0);
				}

				function toggle() {
					vid[vid.paused ? 'play' : 'pause']();
				}

				function toggleFullScreen() {
					if ((document.fullScreenElement && document.fullScreenElement !== null) ||
						(!document.mozFullScreen && !document.webkitIsFullScreen)) {
						if (document.documentElement.requestFullScreen) {
							document.documentElement.requestFullScreen();
						} else if (document.documentElement.mozRequestFullScreen) {
							document.documentElement.mozRequestFullScreen();
						} else if (document.documentElement.webkitRequestFullScreen) {
							document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
						}
					} else {
						if (document.cancelFullScreen) {
							document.cancelFullScreen();
						} else if (document.mozCancelFullScreen) {
							document.mozCancelFullScreen();
						} else if (document.webkitCancelFullScreen) {
							document.webkitCancelFullScreen();
						}
					}
				}

				init();
			</script>
		</body>

	</html>
EOF;

	file_put_contents("$dir/index.html", $html);
	pclose(popen(sprintf('start /max "" "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" "%s"', "$dir/index.html"), "r"));
}

function files($dir, $level = 0) {
	global $fix;

	$files = scandir($dir);
	$results = [];
	$maxHeight = 1080;
	$i = 0;

	foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);

        if (!is_dir($path)) {
			$ext = pathinfo($path, PATHINFO_EXTENSION);
			
			if (preg_match('/avi|flv|mp4|mov/i', $ext)) {
				$fix = ($fix || isHuge($path, $maxHeight));

				if (!$fix && preg_match('/mp4/i', $ext)) {
					$results[val($value, $level, ++$i)] = $path;
				} else {
					$newPath = sprintf('%s/%s-fix.mp4', pathinfo($path, PATHINFO_DIRNAME), pathinfo($path, PATHINFO_FILENAME));
					$command = sprintf('HandBrakeCLI.exe --encoder x264 --quality 32.0 --audio 1 --aencoder faac --ab 48 --mixdown mono --arate auto --drc 0.0 --audio-copy-mask aac,ac3,dtshd,dts,mp3 --audio-fallback ffac3 --format mp4 --loose-anamorphic --modulus 2 --markers --x264-preset medium --h264-profile baseline --h264-level 3.0 --x264-tune fastdecode --optimize --maxWidth 1280 --maxHeight 720 --crop 0:0:0:0 --ipod-atom --input "%s" --output "%s"', $path, $newPath);

					system($command); 
					$results[val($value, $level, ++$i)] = $newPath;
					
					if (filesize($newPath) > 1024 * 1024) {
						unlink($path);
					}
				}
			}
        } else if($value != "." && $value != "..") {
            $results = array_merge($results, files($path, ++$level));
        }
    }

	ksort($results);

	return $results;
}

function isHuge($path, $maxHeight) {
	$res = `ffmpeg -i "$path" 2>&1`;
	
	if (preg_match('/Stream.*, ((\d+)x(\d+))/', $res, $matches)) {
		return $matches[3] > $maxHeight;
	}

	return false;
}

function val($name, $prefix, $i) {
	list($num, $fn) = preg_match("/^(\d+)[\s\.\-]+(.*)/", $name, $matches) ? [$matches[1], $matches[2]] : [$i, $name];
	return sprintf("%02d - %03d - %s", $prefix,  $num, $fn);
}