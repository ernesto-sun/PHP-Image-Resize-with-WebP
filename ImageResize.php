<?php

const IMAGE_MIME = [ 'image/jpeg', 'image/gif', 'image/png', 'image/webp' ];    

class Image
{
    public $source;
    public $sourceWidth;
    public $sourceHeight;
    public $sourceMime;
    
    public $outputWidth;
    public $outputHeight;
    
    public $background;
    public $border;
    public $quality;
    public $padding;
    public $transparent;
    
    public $filename;
    public $input;
    public $output;
    public $extension;
    public $basename;
    
    public $remote;
    public $rename;   // auto-renaming
        
    function __construct( $image = null, $errorMode = 'json' )
    {
        $this->rename      = true;
        $this->quality     = 100;
        $this->padding     = 0;
        $this->transparent = true;
        
        $border = new \stdClass();
        $border->width = 0;
        $border->color = $this->hex2rgb('#fff');
        
        $this->border = $border;
        
        $this->background = [
            'r' => 0,
            'g' => 0,
            'b' => 0
        ];
        
        if( !is_null($image) )
        {
            if( !is_string($image) )
            {
                throw new \Exception('Image parameter must be passed as a string.');
            }
            
            $info = getimagesize($image);
            
            # File doesn't exist:
            if( !$info )
            {
                throw new \Exception('Source image does not exist.');
            }
            
            # Make sure it's supported:
            if( !in_array($info['mime'], IMAGE_MIME) )
            {
                throw new \Exception('Image has an invalid MIME type ('.$info['mime'].').');
            }
            
            $this->source       = $image;
            $this->filename     = basename($image);
            $this->sourceWidth  = $info[0];
            $this->sourceHeight = $info[1];
            $this->sourceMime   = $info['mime'];
            $this->remote       = (filter_var($this->source, FILTER_VALIDATE_URL));
            
            # Load it:
            $this->input = $this->output = imagecreatefromstring( file_get_contents($this->source) );
            
            $nameParts = explode('.', $this->filename);
            $this->extension = end($nameParts);
            
            array_pop($nameParts);
            $this->basename = implode('.', $nameParts);
        }
        else
        {
            $this->source       = null;
            $this->sourceWidth  = 0;
            $this->sourceHeight = 0;
            $this->sourceMime   = null;
            $this->filename     = null;
            $this->input        = null;
            $this->output       = null;
        }
        
        $this->outputWidth  = $this->sourceWidth;
        $this->outputHeight = $this->sourceHeight;
    }
    
        
    # Set the background color:
    public function setBackgroundColor( $bg = '#000000' )
    {
        $this->background = $this->hex2rgb( $bg );
    }
        
    # Set border:
    public function setBorder( int $width, string $color = '#ffffff' )
    {
        $this->border->width = $width;
        $this->border->color = $this->hex2rgb($color);
    }
    
    # Set border width:
    public function setBorderWidth( int $width )
    {
        $this->border->width = $width;
    }
    
    # Set border color:
    public function setBorderColor( string $color = '#ffffff' )
    {
        $this->border->color = $this->hex2rgb($color);
    }

    # Get the avergae colour of the image:
    public function getAverageColor()
    {
        $sample = imagecreatetruecolor(1, 1);
        
        imagecopyresampled($sample, $this->input, 0, 0, 0, 0, 1, 1, $this->sourceWidth, $this->sourceHeight);
        
        $rgb   = imagecolorat($sample, 0, 0);
        $color = imagecolorsforindex($sample, $rgb);
        
        $rgb = [
            'r' => round(round(($color['red'] / 0x33)) * 0x33),
            'g' => round(round(($color['green'] / 0x33)) * 0x33),
            'b' => round(round(($color['blue'] / 0x33)) * 0x33)
        ];
        
        return sprintf('#%02X%02X%02X', $rgb['r'], $rgb['g'], $rgb['b']);
    }
    
    
    # START FILTER FUNCTIONS:
    # Make the image greyscale:
    public function greyscale()
    {
        imagefilter( $this->output, IMG_FILTER_GRAYSCALE);
    }
    
    # Invert the colours:
    public function invert()
    {
        imagefilter( $this->output, IMG_FILTER_NEGATE );
    }
    
    # Set the brightness:
    public function setBrightness( int $brightness )
    {
        imagefilter( $this->output, IMG_FILTER_BRIGHTNESS, $brightness );
    }
    
    # Set the contrast:
    public function setContrast( int $contrast )
    {
        imagefilter( $this->output, IMG_FILTER_CONTRAST, $contrast );
    }
    
    # Set the saturation:
    public function setSaturation( float $saturation )
    {
        # GD has no saturation setting, so we wrote one!
    
        for( $x = 0; $x < $this->outputWidth; $x++ )   
        {  
            for( $y = 0; $y < $this->outputHeight; $y++ )   
            {  
                 $rgb = imagecolorat($this->output, $x, $y);
                 
                 $r   = ($rgb >> 16) & 0xFF;  
                 $g   = ($rgb >> 8) & 0xFF;  
                 $b   = $rgb & 0xFF;  
                 $alpha = ($rgb & 0xFF000000) >> 24;
                 
                 # convert to HSL:
                 list($h, $s, $v) = $this->rgb2hsv($r, $g, $b);
                 
                 # Add the saturation multiplier:
                 $s = $s * $saturation;
                 
                 if( $s > 1 )
                 {
                    $s = 1;
                 }
                 
                 # Convert back to RGB:
                 list($r, $g, $b) = $this->hsv2rgb($h, $s, $v);
                 
                 # Set the pixels:
                 imagesetpixel($this->output, $x, $y, imagecolorallocatealpha($this->output, $r, $g, $b, $alpha));  
            }  
        }  
    }
    
    # Colorize:
    public function colorize( $hex )
    {
        $color = $this->hex2rgb($hex);
        
        imagefilter( $this->output, IMG_FILTER_COLORIZE, $color['r'], $color['g'], $color['b'] );
    }
    
    # END FILTER FUNCTIONS;
    
    # BEGIN RESIZE FUNCTIONS
    
    # Resize the image, and do not respect ratio:
    public function resize( int $width, int $height = null )
    {
        if( is_null($height) )
        {
            $height = $width;
        }
        
        $this->outputWidth  = $width;
        $this->outputHeight = $height;
        
        $this->output = imagecreatetruecolor($width, $height);
        
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($this->output, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($this->output, 0, 0, $bg);
        }
        
        # Set transparent:
        else
        {
            $bg = imagecolorallocate($this->output, 0, 0, 0);
            
            imagecolortransparent($this->output, $bg);
            imagealphablending($this->output, false);
            imagesavealpha($this->output, true);
        }
        
		imagecopyresampled($this->output, $this->input, 0, 0, 0, 0, $width, $height, $this->sourceWidth, $this->sourceHeight);
    }
    
    # Resize the image width and respect the ratio:
    public function resizeWidth( int $width )
    {
        $height = round($this->sourceHeight * ($width / $this->sourceWidth));
        
        $this->outputWidth  = $width;
        $this->outputHeight = $height;

		$this->output = imagecreatetruecolor($width, $height);
        
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($this->output, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($this->output, 0, 0, $bg);
        }
        
        # Set transparent:
        else
        {
            $bg = imagecolorallocate($this->output, 0, 0, 0);
            
            imagecolortransparent($this->output, $bg);
            imagealphablending($this->output, false);
            imagesavealpha($this->output, true);
        }
		
		imagecopyresampled($this->output, $this->input, 0, 0, 0, 0, $width, $height, $this->sourceWidth, $this->sourceHeight);
    }
    
    # Resize the image height and respect the ratio:
    public function resizeHeight( int $height )
    {
        $width = round($this->sourceWidth * ($height / $this->sourceHeight));
		
        $this->outputWidth  = $width;
        $this->outputHeight = $height;
        
		$this->output = imagecreatetruecolor($width, $height);
        
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($this->output, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($this->output, 0, 0, $bg);
        }
        
        # Set transparent:
        else
        {
            $bg = imagecolorallocate($this->output, 0, 0, 0);
            
            imagecolortransparent($this->output, $bg);
            imagealphablending($this->output, false);
            imagesavealpha($this->output, true);
        }
		
		imagecopyresampled($this->output, $this->input, 0, 0, 0, 0, $width, $height, $this->sourceWidth, $this->sourceHeight);
    }
    
    # Resize the image, respecting the ratio, and crop to specified dimensions:
    public function resizeCrop( int $width, int $height = null )
    {
        if( is_null($height) )
        {
            $height = $width;
        }
        
        // First, we need to resize it:
		$this->output = imagecreatetruecolor($width, $height);
        
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($this->output, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($this->output, 0, 0, $bg);
        }
        
        # Set transparent:
        else
        {
            $bg = imagecolorallocate($this->output, 0, 0, 0);
            
            imagecolortransparent($this->output, $bg);
            imagealphablending($this->output, false);
            imagesavealpha($this->output, true);
        }
		
		$ratio = $width / $this->sourceWidth;
		$new_w = $width;
		$new_h = $this->sourceHeight * $ratio;
	
        if($new_h < $height)
		{
			$ratio = $height / $this->sourceHeight;
			$new_h = $height;
			$new_w = $this->sourceWidth * $ratio;
		}
        
        $image2 = imagecreatetruecolor($new_w, $new_h);
        
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($image2, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($image2, 0, 0, $bg);
        }
		# Set transparent:
        else
        {
            $bg = imagecolorallocate($image2, 0, 0, 0);
            
            imagecolortransparent($image2, $bg);
            imagealphablending($image2, false);
            imagesavealpha($image2, true);
        }
        
		imagecopyresampled($image2, $this->input, 0, 0, 0, 0, $new_w, $new_h, $this->sourceWidth, $this->sourceHeight);

		if(($new_h != $height) || ($new_w != $width))
		{
            if($new_h > $height)
			{
                $extra = $new_h - $height;
				$x = 0;
				$y = round($extra / 2);
				imagecopyresampled($this->output, $image2, 0, 0, $x, $y, $width, $height, $width, $height);
			}
			else
			{
				$extra = $new_w - $width;
				$x = round($extra / 2);
				$y = 0; 
				imagecopyresampled($this->output, $image2, 0, 0, $x, $y, $width, $height, $width, $height);
			}
            
            $this->outputWidth  = $width;
            $this->outputHeight = $height;
            
			imagedestroy($image2);
		}
		else
		{
            $this->outputWidth  = $new_w;
            $this->outputHeight = $new_h;
        
			imagecopyresampled($this->output, $this->input, 0, 0, 0, 0, $new_w, $new_h, $this->sourceWidth, $this->sourceHeight);
		}
    }
    
    # Contain the original image in a new image with given dimensions:
    public function contain( int $width, int $height = null, int $padding = null )
    {
        if( is_null($height) )
        {
            $height = $width;
        }
        
        $this->outputWidth  = $width;
        $this->outputHeight = $height;
        
        $this->output = imagecreatetruecolor($width, $height);
        
        $padding = (!is_null($padding)) ? $padding : $this->getPadding();
        
		$w = $this->sourceWidth;
		$h = $this->sourceHeight;	    
		
		if($w < $width && $h < $height)
		{
			$x = round(($width - $w) / 2);
			$y = round(($height - $h) / 2);
			$new_h = $h;
			$new_w = $w;
		}
		else
		{
			if($w > $h)
			{
				$new_w = $width - ($padding * 2);
				$new_h = ($h * ($width / $w)) - ($padding * 2);
				
				if($new_h > $height)
				{
					$new_h = $height - ($padding * 2);
					$new_w = $w * ($height / $h);
				}
			}
			else
			{
				$new_h = $height - ($padding * 2);
				$new_w = ($w * ($height / $h)) - ($padding * 2);
			}
			
			$x = round(($width - $new_w) / 2);
			$y = round(($height - $new_h) / 2);
		}
        
        # No transparency, set background:
        if( ! $this->transparent )
        {
            $bg   = imagecolorallocate($this->output, $this->background['r'], $this->background['g'], $this->background['b']);
            imagefill($this->output, 0, 0, $bg);
        }
        
        # Set transparent:
        else
        {
            # Create a transparent image:
            imagesavealpha($this->output, true);
            
            $bg = imagecolorallocatealpha($this->output, 0, 0, 0, 127);
            imagefill($this->output, 0, 0, $bg);
        }
        
        imagecopyresampled($this->output, $this->input, $x, $y, 0, 0, $new_w, $new_h, $w, $h);
		
    }
    # END RESIZE FUNCTIONS
    
    # Output the image using the original type:
    public function output($filename)
    {
        $func = $this->getGDFn($this->sourceMime);
        
        $this->handleOutput($filename, $func);
    }
    
    # Output as a JPEG:
    public function outputJPEG($filename)
    {
        $this->handleOutput($filename, 'imagejpeg');
    }
    
    # Output as a PNG:
    public function outputPNG($filename)
    {
        $this->handleOutput($filename, 'imagepng');
    }
    
    # Output as a GIF:
    public function outputGIF($filename)
    {
        $this->handleOutput($filename, 'imagegif' );
    }

    # Output as a WEBP:
    public function outputWEBP($filename)
    {
        $this->handleOutput($filename, 'imagewebp' );
    }
    
    # Download as original:
    public function download( string $filename = null )
    {
        $filename = (is_null($filename)) ? $this->filename : $this->removeExtension($filename).'.'.$this->extension;
        
        header('Content-disposition: attachment; filename="'.$filename.'"');
        
        $this->output(false);
    }
    

    # Download as JPEG:
    public function downloadJPEG( string $filename = null )
    {
        $filename = (is_null($filename)) ? $this->basename.'.jpg' : $this->removeExtension($filename).'.jpg';
        
        header('Content-disposition: attachment; filename="'.$filename.'"');
        
        $this->outputJPEG(false);
    }
    

    # Download as PNG:
    public function downloadPNG( string $filename = null )
    {
        $filename = (is_null($filename)) ? $this->basename.'.png' : $this->removeExtension($filename).'.png';
        
        header('Content-disposition: attachment; filename="'.$filename.'"');
        
        $this->outputPNG(false);
    }
    
    # Download as GIF:
    public function downloadGIF( string $filename = null )
    {
        $filename = (is_null($filename)) ? $this->basename.'.gif' : $this->removeExtension($filename).'.gif';
        
        header('Content-disposition: attachment; filename="'.$filename.'"');
        
        $this->outputGIF(false);
    }

    # Download as WEBP:
    public function downloadWEBP( string $filename = null )
    {
        $filename = (is_null($filename)) ? $this->basename.'.webp' : $this->removeExtension($filename).'.webp';
        
        header('Content-disposition: attachment; filename="'.$filename.'"');
        
        $this->outputWEBP(false);
    }
    
    # Handle the output:
    private function handleOutput($filename, $func)
    {
        # Do we need a border?
        if( $this->border->width > 0 )
        {
            $x1 = 0; 
            $y1 = 0; 
            $x2 = imagesx($this->output) - 1; 
            $y2 = imagesy($this->output) - 1; 
        
            for($i = 0; $i < $this->border->width; $i++) 
            { 
                imagerectangle($this->output, $x1++, $y1++, $x2--, $y2--, imagecolorallocate($this->output, $this->border->color['r'], $this->border->color['g'], $this->border->color['b'])); 
            } 
        }
        
        if(isset($filename) && !empty($filename))
        {
            if( $func != 'imagewbmp' )
            {
                $func( $this->output, $filename, $this->getQualityParam($func) );
            }
            else
            {
                $func( $this->output, $$filename );
            }
        }
        else
        {
            header('Content-type: '.$this->getMimeType($func));
            if( $func != 'imagewbmp' )
            {
                $func( $this->output, null, $this->getQualityParam($func) );
            }
            else
            {
                $func( $this->output, null );
            }
        }
        
        $this->cleanup();
    }
    
    # Get the quality parameter for a GD function:
    private function getQualityParam( $func )
    {
        # If it's a PNG, it's compression, not quality! 0 = no compression:
        if( $func == 'imagepng' )
        {
            return abs( round((0 - ($this->quality / 100)) * 9) );
        }
        
        return $this->quality;
    }
    
    # Get the MIME type for a given fn:
    private function getMimeType( $func )
    {
        switch( $func )
        {
            case 'imagejpeg':
            default:
                return 'image/jpeg';
            
            case 'imagepng':
                return 'image/png';
            
            case 'imagegif':
                return 'image/gif';
            
            case 'imagewbmp':
                return 'image/bmp';

            case 'imagewwebp':
                return 'image/webp';
    
        }
    }
    
    # Get the GD function name from the mime:
    private function getGDFn( $mime )
    {
        switch( $mime )
        {
            default:
                return str_replace('/', '', $mime);
                break;
        }
    }
    
    # Convert hex to RGB:
    private function hex2rgb( $hex )
    {
        $hex = str_replace('#', '', $hex);
        
        $hex      = str_replace('#', '', $hex);
        $length   = strlen($hex);
        $rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
        $rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
        $rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));

        return $rgb;
    }
    
    # Convert RGB to HSV:
    private function rgb2hsv($r, $g, $b)   
    {  
        $newR = ($r / 255);  
        $newG = ($g / 255);  
        $newB = ($b / 255);  
        $rgbMin = min($newR, $newG, $newB);  
        $rgbMax = max($newR, $newG, $newB);  
        $chroma = $rgbMax - $rgbMin;  
        $v = $rgbMax;  
        
        if ($chroma == 0)   
        {  
            $h = 0;  
            $s = 0;  
        }   
        else   
        {  
            $s = $chroma / $rgbMax;  
            
            $chromaR = ((($rgbMax - $newR)/6) + ($chroma/2))/$chroma;  
            $chromaG = ((($rgbMax - $newG)/6) + ($chroma/2))/$chroma;  
            $chromaB = ((($rgbMax - $newB)/6) + ($chroma/2))/$chroma;
            
            if( $newR == $rgbMax )
            {
                $h = $chromaB - $chromaG;
            }
            elseif( $newG == $rgbMax )
            {
                $h = ( 1 / 3 ) + $chromaR - $chromaB;
            }
            elseif( $newB == $rgbMax )
            {
                $h = ( 2 / 3 ) + $chromaG - $chromaR;
            }
            
            if( $h < 0 )
            {
                $h++;
            }
            
            if( $h > 1 )
            {
                $h--;
            }
        }  
        
        return [ $h, $s, $v ];
    }
    
    # Convert HSV to RGB:
    private function hsv2rgb($h, $s, $v)   
    {  
        if($s == 0)   
        {  
            $r = $g = $b = $v * 255;  
        }   
        else   
        {  
            $newH = $h * 6;  
            $i = floor( $newH );  
            
            $var_1 = $v * ( 1 - $s );  
            $var_2 = $v * ( 1 - $s * ( $newH - $i ) );  
            $var_3 = $v * ( 1 - $s * (1 - ( $newH - $i ) ) );  
            
            if( $i == 0 )
            {
                $newR = $v;
                $newG = $var_3;
                $newB = $var_1;
            }
            
            elseif( $i == 1 )
            {
                $newR = $var_2;
                $newG = $v;
                $newB = $var_1;
            }
            
            elseif( $i == 2 )
            {
                $newR = $var_1;
                $newG = $v;
                $newB = $var_3;
            }
            
            elseif( $i == 3 )
            {
                $newR = $var_1;
                $newG = $var_2;
                $newB = $v;
            }
            
            elseif( $i == 4 )
            {
                $newR = $var_3;
                $newG = $var_1;
                $newB = $v;
            }  
            
            else
            {
                $newR = $v;
                $newG = $var_1;
                $newB = $var_2;
            }
            
            $r = $newR * 255;  
            $g = $newG * 255;  
            $b = $newB * 255;  
        }   
        
        return [ $r, $g, $b ];  
    }  
    
    # Remove an extension:
    private function removeExtension( $filename )
    {
        $parts = explode('.', $filename);
        array_pop($parts);
        
        return implode('.', $parts);
    }
    
    # Clean up resources:
    private function cleanup()
    {
        imagedestroy($this->input);
        imagedestroy($this->output);
    }
    
}
