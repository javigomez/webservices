<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_webservices
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Webservices component helper.
 *
 * @since  1.0
 */
class WebservicesHelper extends JHelperContent
{
	/**
	 * Configure the Linkbar.
	 *
	 * @param   string  $vName  The name of the active view.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public static function addSubmenu($vName)
	{
		JHtmlSidebar::addEntry(
			JText::_('COM_WEBSERVICES_WEBSERVICES_MANAGE'),
			'index.php?option=com_webservices&view=webservices',
			$vName == 'webservices'
		);
	}

	/**
	 * Checks if the file can be uploaded.
	 *
	 * @param   string  $name  Additional string you want to put into hash
	 *
	 * @return  boolean
	 */
	public static function getUniqueName($name = '')
	{
		// Get a (very!) randomised name
		if (version_compare(JVERSION, '3.0', 'ge'))
		{
			$serverKey = JFactory::getConfig()->get('secret', '');
		}
		else
		{
			$serverKey = JFactory::getConfig()->getValue('secret', '');
		}

		$sig = $name . microtime() . $serverKey;

		if (function_exists('sha256'))
		{
			$mangledName = sha256($sig);
		}
		elseif (function_exists('sha1'))
		{
			$mangledName = sha1($sig);
		}
		else
		{
			$mangledName = md5($sig);
		}

		return $mangledName;
	}

	/**
	 * Uploads file to the given media folder.
	 *
	 * @param   array   $files              The array of Files (file descriptor returned by PHP)
	 * @param   string  $destinationFolder  Name of a folder in media/com_redcore/.
	 * @param   array   $options            Array of options for check
	 *                         maxFileSize              => Maximum allowed file size. Set 0 to disable check
	 *                         allowedFileExtensions    => Comma separated string list of allowed file extensions.
	 *                         allowedMIMETypes         => Comma separated string list of allowed MIME types.
	 *                         setUniqueFileName        => If set this will mangle destination file name
	 *                         overrideExistingFile     => If set this will override File with the same name if it exists
	 *
	 * @return array|bool
	 */
	public static function uploadFiles($files, $destinationFolder, $options = array())
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.path');
		$app = JFactory::getApplication();
		$resultFile = array();

		foreach ($files as &$file)
		{
			// Get unique name
			if (!empty($options['setUniqueFileName']))
			{
				$fileExtension = JFile::getExt($file['name']);
				$file['destinationFileName'] = self::getUniqueName($file['name']) . '.' . $fileExtension;
			}
			else
			{
				$file['destinationFileName'] = JFile::makeSafe($file['name']);
			}

			// Get full path
			$file['filePath'] = JPath::clean($destinationFolder . '/' . $file['destinationFileName']);

			// Can we upload this file type?
			if (!self::canUpload($file, $options))
			{
				return false;
			}
		}

		JPluginHelper::importPlugin('content');

		foreach ($files as &$file)
		{
			// Trigger the onContentBeforeSave event.
			$objectFile = new JObject($file);
			$result = JFactory::getApplication()->triggerEvent('onContentBeforeSave', array('com_redcore.file', &$objectFile, true));

			if (in_array(false, $result, true))
			{
				// There are some errors in the plugins
				$errors = $objectFile->getErrors();
				$app->enqueueMessage(JText::sprintf('LIB_WEBSERVICES_ERROR_BEFORE_SAVE', implode('<br />', $errors)), 'error');

				return false;
			}

			if (!JFile::upload($objectFile->tmp_name, $objectFile->filePath))
			{
				// Error in upload
				$app->enqueueMessage(JText::_('LIB_WEBSERVICES_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');

				return false;
			}
			else
			{
				// Trigger the onContentAfterSave event.
				JFactory::getApplication()->triggerEvent('onContentAfterSave', array('com_redcore.file', &$objectFile, true));
			}

			$resultFile[] = array(
				'original_filename' => $objectFile->name,
				'uploaded_filename' => $objectFile->destinationFileName,
				'mime_type' => !empty($objectFile->mimeTypeName) ? $objectFile->mimeTypeName : self::getMimeType($file),
				'filepath' => $objectFile->filePath
			);
		}

		// Return the file info
		return $resultFile;
	}

	/**
	 * Checks if the file can be uploaded.
	 *
	 * @param   array  $file     File information.
	 * @param   array  $options  Array of options for check
	 *
	 * @return  boolean
	 */
	private static function canUpload($file, $options = array())
	{
		jimport('joomla.filesystem.file');
		$app = JFactory::getApplication();

		$file['name'] = JFile::makesafe($file['name']);

		if (empty($file['name']))
		{
			$app->enqueueMessage(JText::_('LIB_WEBSERVICES_ERROR_WARNFILENAME'), 'error');

			return false;
		}

		// Allowed file extensions
		if (!empty($options['allowedFileExtensions']))
		{
			$format = strtolower(JFile::getExt($file['name']));
			$allowable = array_map('trim', explode(",", $options['allowedFileExtensions']));

			if (!in_array($format, $allowable))
			{
				$app->enqueueMessage(JText::sprintf('LIB_WEBSERVICES_ERROR_WARNFILETYPE', $format, $options['allowedFileExtensions']), 'error');

				return false;
			}
		}

		// If not set from options then Max file size is set from php.ini
		if (!isset($options['maxFileSize']))
		{
			$options['maxFileSize'] = (int) (ini_get('upload_max_filesize'));
		}

		$options['maxFileSize'] = (int) ($options['maxFileSize'] * 1024 * 1024);

		if ($options['maxFileSize'] != 0 && (int) $file['size'] > $options['maxFileSize'])
		{
			$app->enqueueMessage(JText::sprintf('LIB_WEBSERVICES_ERROR_WARNFILETOOLARGE', $file['size'], $options['maxFileSize']), 'error');

			return false;
		}

		// Allowed file extensions
		if (!empty($options['allowedMIMETypes']))
		{
			$validFileTypes = array_map('trim', explode(",", $options['allowedMIMETypes']));

			$file['mimeTypeName'] = self::getMimeType($file);

			if (strlen($file['mimeTypeName']) && !in_array($file['mimeTypeName'], $validFileTypes))
			{
				$app->enqueueMessage(
					JText::sprintf('LIB_WEBSERVICES_ERROR_WARNINVALID_MIME', $file['mimeTypeName'], $options['allowedMIMETypes']),
					'error'
				);

				return false;
			}
		}

		// If we have a name clash, abort the upload
		if (empty($options['overrideExistingFile']) && JFile::exists($file['filePath']))
		{
			$app->enqueueMessage(JText::sprintf('LIB_WEBSERVICES_ERROR_FILE_EXISTS', $file['destinationFileName']), 'error');

			return false;
		}

		return true;
	}

	/**
	 * Get Mime type
	 *
	 * @param   array  $file  File information.
	 *
	 * @return  string
	 */
	private static function getMimeType($file)
	{
		if (function_exists('finfo_open'))
		{
			// We have fileinfo
			$finfo = finfo_open(FILEINFO_MIME);
			$type  = finfo_file($finfo, $file['tmp_name']);

			finfo_close($finfo);
		}
		elseif (function_exists('mime_content_type'))
		{
			// We have mime magic.
			$type = mime_content_type($file['tmp_name']);
		}
		else
		{
			$type = $file['type'];
		}

		// Resolves problem of adding charset to the mime type
		$type = explode(';', $type);

		return $type[0];
	}
}
