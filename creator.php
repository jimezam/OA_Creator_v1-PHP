<?php

/*
Requires PHP DOM extension
https://www.php.net/en/dom
*/

// copy other files to target

////////////////////////////////////////////////////////////////////////////////

const VERSION = "1.0";  

const VARIABLE_SEPARATOR = "%%";  

$shortopts  = "";

$longopts  = array(
    "help",
    "version",
    "clean",
    "recursive",
    "allowNotEmptyTarget",
    "file::",
    "target::",
    "exclude::"
);

$options = getopt($shortopts, $longopts);

// help
if(isset($options['help']))			
{
	coreHelp();
	exit;
}

// version
if(isset($options['version']))		
{
	coreVersion();
	exit;
}

// clean [path]
if(isset($options['clean']))			
{
	coreClean($options);
	exit;
}

// file [path]
if(isset($options['file']))			
{
	coreBuildOne($options);
	exit;
}

// recursive
if(isset($options['recursive']))		
{
	coreBuildAll($options);
	exit;
}

// Fallback: no valid option selected

echo "Oh oh, no valid option selected, please check --help.\n";
exit;

////////////////////////////////////////////////////////////////////////////////

function coreHelp()
{
	$str  = "php creator [options]\n";
	$str .= "\n";
	$str .= "Options:\n\n";
	$str .= "--help                      shows this help message\n";
	$str .= "--version                   shows the version number of this release\n";
	$str .= "--allowNotEmptyTarget       allows to run with a non empty target directory\n";
	$str .= "--exclude=\"dir1,...\"      excludes the specified directories from build\n";
	$str .= "--file=xxx [--target=yyy]   builds the xxx file on specified target directory\n";
	$str .= "--clean [--target=yyy]      removes all files on target directory\n";
	$str .= "\n";
	$str .= "Examples:\n\n";
	$str .= "Builds source xxx under target yyy/xxx\n";
	$str .= "--file=xxx [--target=yyy] [--allowNotEmptyTarget]\n";
	$str .= "\n";
	$str .= "Builds all files on project under target yyy\n";
	$str .= "--recursive [--target=yyy] [--exclude=\"aaa,bbb,ccc\"] [--allowNotEmptyTarget]\n";
	$str .= "\n";
	$str .= "Developed by Jorge I. Meza <jimezam@autonoma.edu.co>\n";
	$str .= "\n";
	
	echo $str;
}

////////////////////////////////////////////////////////////////////////////////

function coreVersion()
{
	echo VERSION."\n";
}

////////////////////////////////////////////////////////////////////////////////

function rm_r($dir)
{
    if (false === file_exists($dir)) {
        return false;
    }
    
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if ($fileinfo->isDir()) {
            if (false === rmdir($fileinfo->getRealPath())) {
                return false;
            }
        } else {
            if (false === unlink($fileinfo->getRealPath())) {
                return false;
            }
        }
    }

    return rmdir($dir);
}

function coreClean($options)
{
	$target = $options['target'] ?? 'target';
	
	if(!file_exists($target) || is_file($target))
		die("ERROR: I could not find the target directory: {$target}.\n");
	
	rm_r($target);
}

////////////////////////////////////////////////////////////////////////////////

function processIncludeComment($contents, $comment, $filename)
{
	$originalComment = $comment;

	$comment[0] = ' ';

	$json = json_decode($comment);

	if($json === null)
		die ("ERROR, this include comment has some errors: ".trim($comment)."\n");
		
	//////////////////////////////////////////////////////////////////////////

	$templateFile = dirname($filename) . DIRECTORY_SEPARATOR . $json->file;

	if(!isset($json->file))
		die ("ERROR, This comment does not define the template file: ".trim($comment)."\n");

	if(!file_exists($templateFile) | !is_file($templateFile))
		die ("ERROR, I could not find the template file: {$templateFile} referenced by {$filename}.\n");

	//////////////////////////////////////////////////////////////////////////

	$template = file_get_contents($templateFile);

	if(isset($json->variables))
	{
		foreach(get_object_vars($json->variables) as $key=>$value)
		{
			$template = str_replace(
						strtolower(VARIABLE_SEPARATOR.$key.VARIABLE_SEPARATOR),
			                  $value,
			                  $template);
		}
	}

	//////////////////////////////////////////////////////////////////////////

	$ini = strpos($contents, '<!--'.$originalComment.'-->');
	$end = strpos($contents, '-->', $ini)+strlen('-->');
	
	$first = substr($contents, 0, $ini);
	$last  = substr($contents, $end);
	
	$contents = $first . $template . $last;

	return $contents;
}

function coreBuildOne($options)
{
	$target = $options['target'] ?? 'target';

	if(file_exists($target) & is_file($target))
		die("ERROR: there is a file with the same name of --target directory, please remove it: {$target}.\n");

	if(!file_exists($target))
		mkdir($target, 0777, true);

	if(!isset($options['allowNotEmptyTarget']) && count(scandir($target)) > 2)  // . and ..
		die("ERROR: --target directory is not empty, please clean it: {$target}.\n");
	
	//////////////////////////////////////////////////////////////////////////

	$filename = $options['file'];

	if(!file_exists($filename) || is_dir($filename))
		die("ERROR: I could not find the input's file: {$filename}.\n");

	$contents = file_get_contents($filename);
		
	$doc = new DOMDocument();
	$control = @$doc->loadXML($contents);

	if($control === false)
	{
		echo "WARNING: the file: {$filename} was not recognized as HTML.\n";
		echo "         I will leave its contents unmodified.\n";
	}
	else
	{
		$xpath = new DOMXPath($doc);

		//////////////////////////////////////////////////////////////////////////

		foreach ($xpath->query('//comment()') as $comment)
		{
			if($comment->textContent[0] == '*')
				$contents = processIncludeComment($contents, $comment->textContent, $filename);
		}

		//////////////////////////////////////////////////////////////////////////
	}

	// Build output destination path + filename

	$pathInfo = pathinfo($filename);

	$targetPath = $target . DIRECTORY_SEPARATOR . $pathInfo['dirname'];

	$output = $targetPath . DIRECTORY_SEPARATOR . $pathInfo['basename'];

	// Create output's destination path
	
	if(!file_exists($targetPath))
		mkdir($targetPath, 0777, true);

	// Check output's filename does not exists
	
	if(!isset($options['allowNotEmptyTarget']) && file_exists($output))
		die("ERROR: the output's file already exists, you must clean target before: {$output}.\n");
	
	// Write the output's contents

	file_put_contents($output, $contents);
}


////////////////////////////////////////////////////////////////////////////////

function getDirContents($dir, $includeExtensions, $excludeDirectories) 
{
	$results = array();
	$files = scandir($dir);

	array_walk($excludeDirectories, function(&$value, $key) {
		$value = realpath($value);
	});

	foreach ($files as $key => $value) 
	{
		$path = realpath($dir . DIRECTORY_SEPARATOR . $value);

		if (!is_dir($path)) 
		{
			$extension = pathinfo($path, PATHINFO_EXTENSION);

			if(in_array($extension, $includeExtensions))
				$results[] = $dir . DIRECTORY_SEPARATOR . $value;
		} 
		else 
		{	
			if(in_array($path, $excludeDirectories))
				continue;
			
			if ($value != "." && $value != "..") 
			{
				$results = array_merge($results, 
							     getDirContents($dir . DIRECTORY_SEPARATOR . $value, 
							                    $includeExtensions, 
							                    $excludeDirectories));
			}
		}
	}

	return $results;
}

function coreBuildAll($options)
{
	$target = $options['target'] ?? 'target';

	$workingDirectory = '.';  //getcwd();

	$excludes = array_filter(array_merge([$target], explode(',', $options['exclude'] ?? "")));
	
	var_dump($excludes);

	//////////////////////////////////////////////////////////////////////////

	if(file_exists($target) & is_file($target))
		die("ERROR: there is a file with the same name of --target directory, please remove it: {$target}.\n");

	if(!file_exists($target))
		mkdir($target, 0777, true);

	if(!isset($options['allowNotEmptyTarget']) && count(scandir($target)) > 2)  // . and ..
		die("ERROR: --target directory is not empty, please clean it: {$target}.\n");
	
	//////////////////////////////////////////////////////////////////////////

	$files = getDirContents($workingDirectory, ['html'], $excludes);

	foreach ($files as $key => $value)
	{
		$localOptions = $options;

		$localOptions['file'] = $value;
		
		coreBuildOne($localOptions);
	}

	//////////////////////////////////////////////////////////////////////////	
}

////////////////////////////////////////////////////////////////////////////////

