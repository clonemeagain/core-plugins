<?php

/**
 * This file was created to be used as an "include" in the class.thread.php file to customize the staff ticket thread view.
 * Allowed formats as input into admin interface: (Admin -> Settings -> Tickets, or /scp/settings.php?t=tickets)
 * 												.doc
 * 												.pdf
 * 												.xls
 * 												.xlsx
 * 												.jpg
 * 												.gif
 * 												.png
 * 												.jpeg
 * 												.txt
 * 												.html
 * 												.htm
 *                                                                                              .msg
 *
 * Requires ImageMagick: http://www.imagemagick.org/
 * 
 * Now also requires PHP MimeMailparser.class.php and attachment.class.php from Google.
 * 
 * 
 * 
 * Currently only configured for Linux
 * 
 * How to Use:
 * 
 * Edit scp.css, add the .rotated class:
 * 
 * 
.rotated {
    filter: progid:DXImageTransform.Microsoft.BasicImage(rotation=2);
    -moz-transform: rotate(180deg);
    -webkit-transform: rotate(180deg);
    -moz-transform: rotate(180deg);
    transform: rotate(180deg);
}
 * 
 * Save this file into /includes/ 
 * Add an include('this_file_name.php'); on or abouts line 456 in class.thread.php,
 * E.G: 
 * 	function getAttachmentsLinks($file = 'attachment.php', $target = '', $separator = ' ') {
 * 		$str = '';
 * 		foreach ($this->getAttachments() as $attachment) {
 * 			// The hash can be changed  but must match validation in @file 
 * 			$hash = md5($attachment['file_id'] . session_id() . $attachment['file_hash']);
 * 			$size = '';
 * 				$size = sprintf('<em>(%s)</em>', Format :: file_size($attachment['size']));
 * 				include('tdj_thread.php'); //___________________________add this line here___________________________________
 * 			$str .= sprintf('<a class="Icon file"...
 *
 * Configuration Options: 
 */
/**
 * Set the name of the folder
 *
 * I recommend a cronjob with the following: find ../scp/restricted -type f -mtime +14 -delete
 * To ensure old attachments are removed from the filesystem.
 */
$my_private_folder_name = 'restricted';

/**
 * Set IMG tag sizes, currently set to A4, as per: http://www.a4papersize.org/a4-paper-size-in-pixels.php
 */
$imageSizes = ''; //'width="595" height="842"'; 
/**
 * Set the Base URL.. couldn't be stuffed figuring out the damn $settings->getBaseUrl() class
 */
$u = 'http://intranet.tdj.com.au/support/scp';
/**
 * Change to false to hide from apache error log.. useful while testing
 */
$showInLogs = true;

/**
 * This is the magick! 
 * Uses the lowest CPU priority, converts the image to double size, then drops it down 50%.. makes it look better.
 * If you Up the 144 to 288 you have to change the line :exec("$image... below with a different % resize.
 * If you are not using linux with imagemagick installed.. use whatever program you would use to convert a PDF into a JPG here.
 */
$im_convert_bin = '/usr/bin/convert';

//Better Quality version
$imageMagickCommand = "/usr/bin/nice -n 5 $im_convert_bin -colorspace RGB -density 144";

// Fast version.. might make faxes look like garbage, well, more like garbage..
// Find this line: $execer = "$imageMagickCommand $tfn -resize 50% $thumb"; and drop the '-resize 50%' bit
//$imageMagickCommand = "$im_convert_bin";



/**
 * ---------------------------------------------------------------------------------------------------
 * 
 * Really don't change stuff below here 
 * 
 * ---------------------------------------------------------------------------------------------------
 * Well, unless you know PHP of course..
 */
$id = $attachment['attach_id']; //$attachment is set in parent file, ignore IDE warning.
$dir = "/home/www/intranet/support/scp/$my_private_folder_name";
// Make the folder if it doesn't exist.
if (!is_dir($dir)) {
    error_log("Making dir: $dir");
    mkdir($dir);
}

$thumb = "$dir/t_$id.jpg";
$a = Attachment :: lookup($id);
$f = $a->getFile();

//Route by attachment
$ext = strtolower(substr(strrchr($f->getName(), '.'), 1));

switch ($ext) {
    case 'pdf': {
            if (file_exists($im_convert_bin) != TRUE) {
                error_log("Unable to make thumbnail as no ImageMagick installed");
                break; //skips
            }
            $singleImage = $multiImage = false;
// Check for pre-generated files
            if (file_exists($thumb)) {
                $singleImage = true;
                if ($showInLogs)
                    error_log("Found single-image: $thumb");
            } elseif (file_exists("$dir/t_$id" . '-0.jpg')) {
                $multiImage = true;
                if ($showInLogs)
                    error_log("Found first multi-image!");
            } else {
                
// Generate Preview

                // To start, we'll save the PDF into a temp file
                $temp_pdf = tempnam('/tmp/', 'im_preview');
                
                // Grab file from database 
                // File::getData() is apparently dodgy so this 
                // might not survive the next upgrade
                $pdftext = $f->getData();
                //quickly check how many pages are in the file
                // Thankyou: http://stackoverflow.com/a/1536494/276663
                $num_pages = preg_match_all("/\/Page\W/", $pdftext, $ignoreThis);
                //save it into the temp location
                file_put_contents($temp_pdf, $pdftext);
                unset($pdftext); //free some memory

                if ($num_pages > 1) {
                    // Adjust the command-line to generate previews of ALL pages
                    if ($showInLogs)
                        error_log("Generating $num_pages-page preview");
                    $execThis = "$imageMagickCommand $temp_pdf -density 144 -resize 65% -colorspace sRGB $thumb";
                    $multiImage = true; //needed for display code below
                } else {
                    if ($showInLogs)
                        error_log("Generating single-page preview");
                    //select the first page of the PDF
                    $tfn = $temp_pdf . '[0]'; 
                    $execThis = "$imageMagickCommand $tfn -density 144 -resize 65% -colorspace sRGB $thumb";
                }
                
                // Actually initiate external command
                // takes a while on large PDF's.. :-(
                exec($execThis);
                
            }
// Attach to thread
            $images = glob("$dir/t_$id*");
            $image_index = 0;
            foreach ($images as $image) {
            	
                $thumb_id = $id;
                if ($multiImage) {
                    //Thumbnail id is different on MultiPage previews.
                    $thumb_id = $id . '-' . $image_index++;
                }
                if ($showInLogs)
                    error_log("Attaching preview t_$thumb_id.jpg");
                
                //Correct permissions. (ie, allow me to delete the files later, not locked to web-process
                //chmod ( "$dir/t_$thumb_id.jpg", 0666 );
                // jQuery is only used to select, simple CSS rotate this time.
                $str.= <<<GENERATEJQUERY
<img id=invert$thumb_id src="$u/$my_private_folder_name/t_$thumb_id.jpg" 
     $imageSizes title="PDF Attachment Preview -> Click to rotate 180 degrees"/>
<script type="text/javascript">

	// Remove any previous onClick event listeners, in an attempt to prevent duplicated listeners firing twice!
	$("#invert$thumb_id").off('click','**');
	
	// Attach onClick event listener to image id using jQuery to select and create listener. 
	// Anonymous function for each attachment not that elegant, but works.
    $("#invert$thumb_id").on("click",function(e){ 
		
		// Had to add this to prevent double-action error.. 
		// Not sure why this happened suddenly, might be a jQuery update problem.
		 e.stopImmediatePropagation(); 
		 
		 //Instead of trying to rotate with javascript, simply add or remove the CSS class that will cause the browser to rotate it efficiently.
		$(this).toggleClass("rotated");
		
		// Added to debug as it had stopped working, this showed the handler was being called twice.
		console.log("Inverting preview: $thumb_id");
    });
    //Log attachment of listener into browsers Console. This shows listener being added twice. Gah.
    console.log("Attached event listener to $thumb_id");</script>
<br />
GENERATEJQUERY;
            } 
            break;
        }
    case 'txt':
            $str .= '<pre>' . $f->getData() . '</pre>';
            break;
    case 'htm':
    case 'html':{
            $str .= Format::safe_html($f->getData());
            break;
        }
    case 'jpg':
    case 'gif':
    case 'png':
    case 'jpeg':
    case 'tif': {
            //If attachment is already an image.. 
            // We still gotta get it from the database to make preview.  
            if (file_exists($thumb) != TRUE) {
               
                file_put_contents($thumb, $f->getData());
            }
            $str .= "<img src=\"$u/$my_private_folder_name/t_$id.jpg\" 
                    $imageSizes title=\"Image attachment preview\" /> ";
            break;
        }
    case 'doc':
    case 'docx':  // stupid word docs
    case 'xls':
    case 'xlsx': // stupid excel docs
    default : //Does nothing.
}
?>
