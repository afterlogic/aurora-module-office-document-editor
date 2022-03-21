<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\OfficeDocumentEditor;

use Afterlogic\DAV\FS\File;
use Aurora\Api;
use Afterlogic\DAV\Server;
use Aurora\System\Application;
use Aurora\Modules\Core\Module as CoreModule;
use Aurora\Modules\Files\Module as FilesModule;
use Aurora\Modules\Files\Classes\FileItem;
use Aurora\Modules\OfficeDocumentEditor\Exceptions\Exception;

use function Sabre\Uri\split;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2021, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{

	public $ExtsSpreadsheet = [
		".xls",
		".xlsx",
		".xlsm",
		".xlt",
		".xltx",
		".xltm",
		".ods",
		".fods",
		".ots",
		".csv"
	];
	public $ExtsPresentation = [
		".pps",
		".ppsx",
		".ppsm",
		".ppt",
		".pptx",
		".pptm",
		".pot",
		".potx",
		".potm",
		".odp",
		".fodp",
		".otp"
	];
	public $ExtsDocument = [
		".doc",
		".docx",
		".docm",
		".dot",
		".dotx",
		".dotm",
		".odt",
		".fodt",
		".ott",
		".rtf",
		".txt",
		".html",
		".htm",
		".mht",
		".pdf",
		".djvu",
		".fb2",
		".epub",
		".xps"
	];


	/**
	 * Initializes module.
	 *
	 * @ignore
	 */
	public function init()
	{
		$this->aErrors = [
			Enums\ErrorCodes::ExtensionCannotBeConverted => $this->i18N('ERROR_EXTENSION_CANNOT_BE_CONVERTED')
		];

		$this->AddEntries([
			'editor' => 'EntryEditor',
			'ode-callback' => 'EntryCallback'
		]);

		$this->subscribeEvent('System::RunEntry::before', [$this, 'onBeforeFileViewEntry'], 10);
		$this->subscribeEvent('Files::GetFile', [$this, 'onGetFile'], 10);
		$this->subscribeEvent('Files::GetItems', [$this, 'onGetItems'], 20000);
		$this->subscribeEvent('Files::GetFileInfo::after', [$this, 'onAfterGetFileInfo'], 20000);
		$this->subscribeEvent('AddToContentSecurityPolicyDefault', [$this, 'onAddToContentSecurityPolicyDefault']);
	}

	public function GetSettings()
	{
		Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return [
			'ExtensionsToView' => $this->getOfficeExtensions()
		];
	}

	protected function getExtensionsToView()
	{
		return $this->getConfig('ExtensionsToView', []);
	}

	protected function getExtensionsToConvert()
	{
		return $this->getConfig('ExtensionsToConvert', []);
	}

	protected function getExtensionsToEdit()
	{
		return $this->getConfig('ExtensionsToEdit', []);
	}

	protected function getOfficeExtensions()
	{
		return array_merge(
			$this->getExtensionsToView(),
			$this->getExtensionsToEdit(),
			array_keys($this->getExtensionsToConvert())
		);
	}

	protected function getDocumentType($filename)
	{
		$ext = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));

		if (in_array($ext, $this->ExtsDocument)) return "word";
		if (in_array($ext, $this->ExtsSpreadsheet)) return "cell";
		if (in_array($ext, $this->ExtsPresentation)) return "slide";
		return "";
	}

	protected function isReadOnlyDocument($filename)
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		return in_array($ext, $this->getExtensionsToView());
	}

	protected function documentCanBeEdited($filename)
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		return in_array($ext, $this->getExtensionsToEdit());
	}

	protected function documentCanBeConverted($sFilename)
	{
		$ext = strtolower(pathinfo($sFilename, PATHINFO_EXTENSION));

		return in_array($ext, array_keys($this->getExtensionsToConvert()));
	}

	/**
	 * @param string $sFileName = ''
	 * @return bool
	 */
	public function isOfficeDocument($sFileName = '')
	{
		$sExtensions = implode('|', $this->getOfficeExtensions());
		return !!preg_match('/\.(' . $sExtensions . ')$/', strtolower(trim($sFileName)));
	}

	protected function isTrustedRequest()
	{
		return true; // TODO: find another way to protect dowmload url

		$bResult = false;

		$sTrustedServerHost = $this->getConfig('TrustedServerHost', '');
		if (empty($sTrustedServerHost)) {
			$bResult = true;
		} else {
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			$bResult = $sTrustedServerHost === $ip;
		}

		return $bResult;
	}

	/**
	 *
	 * @param type $aArguments
	 * @param type $aResult
	 */
	public function onBeforeFileViewEntry(&$aArguments, &$aResult)
	{
		$aEntries = [
			'download-file',
			'file-cache',
			'mail-attachment'
		];
		if (in_array($aArguments['EntryName'], $aEntries)) {
			$sEntry = (string) \Aurora\System\Router::getItemByIndex(0, '');
			$sHash = (string) \Aurora\System\Router::getItemByIndex(1, '');
			$sAction = (string) \Aurora\System\Router::getItemByIndex(2, '');

			$aValues = Api::DecodeKeyValues($sHash);

			$sFileName = isset($aValues['FileName']) ? urldecode($aValues['FileName']) : '';
			if (empty($sFileName)) {
				$sFileName = isset($aValues['Name']) ? urldecode($aValues['Name']) : '';
			}
			if ($sAction === 'view' && $this->isOfficeDocument($sFileName) && !isset($aValues['AuthToken'])) {
				$sViewerUrl = './?editor=' . urlencode($sEntry .'/' . $sHash . '/' . $sAction . '/' . time());
				\header('Location: ' . $sViewerUrl);
			} else if ($this->isOfficeDocument($sFileName) || $sFileName === 'diff.zip' || $sFileName === 'changes.json') {
				if ($this->isTrustedRequest()) {
					$sAuthToken = isset($aValues['AuthToken']) ? $aValues['AuthToken'] : null;
					if (isset($sAuthToken)) {
						Api::setAuthToken($sAuthToken);
						Api::setUserId(
							Api::getAuthenticatedUserId($sAuthToken)
						);
					}
				}
			}
		}
	}

	public function EntryEditor()
	{
		$sResult = '';
		$sFullUrl = Application::getBaseUrl();
		$sMode = 'view';
		$fileuri = isset($_GET['editor']) ? $_GET['editor'] : null;
		$filename = null;
		$sHash = null;
		$aHashValues = [];
		$docKey = null;
		$lastModified = time();
		$aHistory = [];

		$oUser = Api::getAuthenticatedUser();

		if (isset($fileuri))  {
			$fileuri = \urldecode($fileuri);
			$aFileuri = \explode('/', $fileuri);
			if (isset($aFileuri[1])) {
				$sHash = $aFileuri[1];
				$aHashValues = Api::DecodeKeyValues($sHash);
				if (!isset($aHashValues['AuthToken'])) {
					$aHashValues['AuthToken'] = Api::UserSession()->Set(
						[
							'token' => 'auth',
							'id' => Api::getAuthenticatedUserId()
						],
						time(),
						time() + 60 * 5 // 5 min
					);
				}
			}
			$sHash = Api::EncodeKeyValues($aHashValues);
			$aFileuri[1] = $sHash;
			$fileuri = implode('/', $aFileuri);
			$fileuri = $sFullUrl . '?' . $fileuri;
		}

		if (isset($sHash)) {
			$aHashValues = Api::DecodeKeyValues($sHash);
			if (isset($aHashValues['FileName'])) {
				$filename = $aHashValues['FileName'];
			} else if (isset($aHashValues['Name'])) {
				$filename = $aHashValues['Name'];
			}
			if (isset($aHashValues['Edit'])) {
				$sMode = 'edit';
			}

			$sHash = Api::EncodeKeyValues($aHashValues);
			$oFileInfo = null;
			try {
				if (isset($aHashValues['UserId'], $aHashValues['Type'], $aHashValues['Path'], $aHashValues['Id'])) {
					$oFileInfo = FilesModule::Decorator()->GetFileInfo(
						$aHashValues['UserId'],
						$aHashValues['Type'],
						$aHashValues['Path'],
						$aHashValues['Id']
					);
				}
			} catch (\Exception $oEx) {}
			
			if ($oFileInfo) {
				$lastModified = $oFileInfo->LastModified;
				$docKey = \md5($oFileInfo->RealPath . $lastModified);
				$oFileInfo->Path = $aHashValues['Path'];
				$sMode = (isset($oFileInfo->ExtendedProps['SharedWithMeAccess']) && ((int) $oFileInfo->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Write || (int) $oFileInfo->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Reshare)) || (!isset($oFileInfo->ExtendedProps['SharedWithMeAccess']) && $oFileInfo->Owner === $oUser->PublicId) ? $sMode : 'view';
				$aHistory = $this->getHistory($oFileInfo, $docKey, $fileuri);
			}
		}

		$bIsReadOnlyMode = ($sMode === 'view') ? true : false;

		$filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$lang = 'en';
		$mode = $bIsReadOnlyMode || $this->isReadOnlyDocument($filename) ? 'view' : 'edit';
		$fileuriUser = '';

		$serverPath = $this->getConfig('DocumentServerUrl' , null);

		$callbackUrl = $sFullUrl . '?ode-callback/' . $sHash;

		if (isset($fileuri) && isset($serverPath)) {
			if ($oUser) {
				$uid = (string) $oUser->Id;
				$uname = !empty($oUser->Name) ? $oUser->Name : $oUser->PublicId;
				$lang = \Aurora\System\Utils::ConvertLanguageNameToShort($oUser->Language);
			}

			$config = [
				"type" => Api::IsMobileApplication() ? "mobile" : 'desktop',
				"documentType" => $this->getDocumentType($filename),
				"document" => [
					"title" => $filename,
					"url" => $fileuri,
					"fileType" => $filetype,
					"key" => $docKey,
					"info" => [
						"owner" => $uname,
						"uploaded" => date('d.m.y', $lastModified)
					],
					"permissions" => [
						"comment" => !$bIsReadOnlyMode,
						"download" => true,
						"edit" => !$bIsReadOnlyMode,
						"fillForms" => !$bIsReadOnlyMode,
						"modifyFilter" => !$bIsReadOnlyMode,
						"modifyContentControl" => !$bIsReadOnlyMode,
						"review" => !$bIsReadOnlyMode,
						"changeHistory" => !$bIsReadOnlyMode
					]
				],
				"editorConfig" => [
					"actionLink" => empty($_GET["actionLink"]) ? null : json_decode($_GET["actionLink"]),
					"mode" => $mode,
					"lang" => $lang,
					"callbackUrl" => $callbackUrl,
					"user" => [
						"id" => $uid,
						"name" => $uname
					],
					"embedded" => [
						"saveUrl" => $fileuriUser,
						"embedUrl" => $fileuriUser,
						"shareUrl" => $fileuriUser,
						"toolbarDocked" => "top",
					],
					"customization" => [
						"chat" => !$bIsReadOnlyMode,
						"comments" => !$bIsReadOnlyMode,
						"about" => false,
						"feedback" => false,
						"goback" => false,
						"forcesave" => true,
						// "logo"=> [
						// 	"image"=> $sFullUrl . 'static/styles/images/logo.png',
						// ],
					]
				]
			];

			$oJwt = new Classes\JwtManager($this->getConfig('Secret', ''));
			if ($oJwt->isJwtEnabled()) {
				$config['token'] = $oJwt->jwtEncode($config);
			}

			$sResult = \file_get_contents($this->GetPath().'/templates/Editor.html');

			$iUserId = Api::getAuthenticatedUserId();
			if (0 < $iUserId) {
				$sResult = strtr($sResult, [
					'{{DOC_SERV_API_URL}}' => $serverPath . '/web-apps/apps/api/documents/api.js',
					'{{CONFIG}}' => \json_encode($config),
					'{{HISTORY}}' => isset($aHistory[0]) ? \json_encode($aHistory[0]) : 'false',
					'{{HISTORY_DATA}}' => isset($aHistory[1]) ? \json_encode($aHistory[1]) : 'false'
				]);
				\Aurora\Modules\CoreWebclient\Module::Decorator()->SetHtmlOutputHeaders();
				@header('Cache-Control: no-cache, no-store, must-revalidate', true);
				@header('Pragma: no-cache', true);
				@header('Expires: 0', true);
			} else {
				Api::Location('./');
			}
		}

		return $sResult;
	}

	public function CreateBlankDocument($Type, $Path, $FileName)
	{
		$mResult = false;
		$ext = strtolower(pathinfo($FileName, PATHINFO_EXTENSION));
		$sFilePath = $this->GetPath() . "/data/new." . $ext;
		if (file_exists($sFilePath)) {
			$rData = \fopen($sFilePath , "r");
			$FileName = FilesModule::Decorator()->GetNonExistentFileName(
				Api::getAuthenticatedUserId(),
				$Type,
				$Path,
				$FileName
			);
			$mResult = $this->createFile(
				Api::getAuthenticatedUserId(), 
				$Type, 
				$Path, 
				$FileName, 
				$rData
			);
			\fclose($rData);

			if ($mResult) {
				$mResult = FilesModule::Decorator()->GetFileInfo(
					Api::getAuthenticatedUserId(), 
					$Type, 
					$Path, 
					$FileName
				);
			}
		}
		return $mResult;
	}

	public function ConvertDocument($Type, $Path, $FileName)
	{
		$mResult = false;
		$aExtensions = $this->getExtensionsToConvert();
		$sExtension = pathinfo($FileName, PATHINFO_EXTENSION);
		$sNewExtension = isset($aExtensions[$sExtension]) ? $aExtensions[$sExtension] : null;
		if ($sNewExtension === null) {
			throw new Exceptions\Exception(Enums\ErrorCodes::ExtensionCannotBeConverted);
		} else {
			$mResult = self::Decorator()->ConvertDocumentToFormat($Type, $Path, $FileName, $sNewExtension);
		}

		return $mResult;
	}

	public function ConvertDocumentToFormat($Type, $Path, $FileName, $ToExtension)
	{
		$mResult = false;
		$oFileInfo = FilesModule::Decorator()->GetFileInfo(
			Api::getAuthenticatedUserId(), 
			$Type, 
			$Path, 
			$FileName
		);
		if ($oFileInfo instanceof  FileItem)
		{
			$sConvertedDocumentUri = null;
			$aPathParts = pathinfo($FileName);
			$sFromExtension = $aPathParts['extension'];
			$sFileNameWOExt = $aPathParts['filename'];
			$sDocumentUri = '';

			if (isset($oFileInfo->Actions['download']['url']))
			{
				$sDownloadUrl = $oFileInfo->Actions['download']['url'];
				$aUrlParts = \explode('/', $sDownloadUrl);
				if (isset($aUrlParts[1]))
				{
					$aUrlParts[1] = $this->GetFileTempHash($aUrlParts[1]);
					$sDocumentUri = Application::getBaseUrl() . \implode('/', $aUrlParts);
					$this->GetConvertedUri(
						$sDocumentUri, 
						$sFromExtension, 
						$ToExtension, 
						'', 
						false, 
						$sConvertedDocumentUri
					);
					if (!empty($sConvertedDocumentUri))
					{
						$rData = \file_get_contents($sConvertedDocumentUri);
						if ($rData !== false)
						{
							$sNewFileName = FilesModule::Decorator()->GetNonExistentFileName(
								Api::getAuthenticatedUserId(),
								$Type,
								$Path,
								$sFileNameWOExt . '.' . $ToExtension
							);
							$mResult = $this->createFile(
								Api::getAuthenticatedUserId(), 
								$Type, 
								$Path, 
								$sNewFileName, 
								$rData
							);
							if ($mResult)
							{
								$mResult = FilesModule::Decorator()->GetFileInfo(
									Api::getAuthenticatedUserId(), 
									$Type, 
									$Path, 
									$sNewFileName
								);
							}

						}

					}
				}
			}
		}

		return $mResult;
	}

	protected function GetFileTempHash($sHash)
	{
		$aValues = Api::DecodeKeyValues($sHash);

		$sFileName = isset($aValues['FileName']) ? urldecode($aValues['FileName']) : '';
		if (empty($sFileName))
		{
			$sFileName = isset($aValues['Name']) ? urldecode($aValues['Name']) : '';
		}
		$aValues['AuthToken'] = Api::UserSession()->Set(
			[
				'token' => 'auth',
				'id' => Api::getAuthenticatedUserId()
			],
			time(),
			time() + 60 * 5 // 5 min
		);

		return Api::EncodeKeyValues($aValues);
	}

	protected function GetConvertedUri($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async, &$converted_document_uri)
	{
		$converted_document_uri = "";
		$responceFromConvertService = $this->SendRequestToConvertService(
			$document_uri, $from_extension, $to_extension, $document_revision_id, $is_async
		);
		$json = \json_decode($responceFromConvertService, true);

		$errorElement = isset($json["error"]) ? $json["error"] : null;
		if ($errorElement != NULL && $errorElement != "") {
			$this->ProcessConvServResponceError($errorElement);
		}

		$isEndConvert = $json["endConvert"];
		$percent = $json["percent"];

		if ($isEndConvert != NULL && $isEndConvert == true) {
			$converted_document_uri = $json["fileUrl"];
			$percent = 100;
		} else if ($percent >= 100) {
			$percent = 99;
		}

		return $percent;
	}

	protected function SendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async)
	{
		$title = basename($document_uri);
		if (empty($title)) {
			$title = \Sabre\DAV\UUIDUtil::getUUID();
		}

		if (empty($document_revision_id)) {
			$document_revision_id = $document_uri;
		}

		$document_revision_id = $this->GenerateRevisionId($document_revision_id);

		$serverPath = $this->getConfig('DocumentServerUrl' , null);
		if ($serverPath !== null) {
			$urlToConverter = $serverPath . '/ConvertService.ashx';
		}

		$arr = [
			"async" => $is_async,
			"url" => $document_uri,
			"outputtype" => trim($to_extension,'.'),
			"filetype" => trim($from_extension, '.'),
			"title" => $title,
			"key" => $document_revision_id
		];

		$headerToken = "";

		$oJwt = new Classes\JwtManager($this->getConfig('Secret', ''));
		if ($oJwt->isJwtEnabled()) {
			$headerToken = $oJwt->jwtEncode([ "payload" => $arr ]);
			$arr["token"] = $oJwt->jwtEncode($arr);
		}

		$opts = [
			'http' => [
				'method'  => 'POST',
				'timeout' => '120000',
				'header'=> 	"Content-type: application/json\r\n" .
							"Accept: application/json\r\n" .
							(empty($headerToken) ? "" : "Authorization: $headerToken\r\n"),
				'content' => \json_encode($arr)
			]
		];

		if (substr($urlToConverter, 0, strlen("https")) === "https") {
			$opts['ssl'] = ['verify_peer' => FALSE];
		}

		$context  = stream_context_create($opts);
		$response_data = file_get_contents($urlToConverter, FALSE, $context);

		return $response_data;
	}

	protected function ProcessConvServResponceError($errorCode)
	{
		$errorMessageTemplate = "Error occurred in the document service: ";
		$errorMessage = '';

		switch ($errorCode) {
			case -8:
				$errorMessage = $errorMessageTemplate . "Error document VKey";
				break;
			case -7:
				$errorMessage = $errorMessageTemplate . "Error document request";
				break;
			case -6:
				$errorMessage = $errorMessageTemplate . "Error database";
				break;
			case -5:
				$errorMessage = $errorMessageTemplate . "Error unexpected guid";
				break;
			case -4:
				$errorMessage = $errorMessageTemplate . "Error download error";
				break;
			case -3:
				$errorMessage = $errorMessageTemplate . "Error convertation error";
				break;
			case -2:
				$errorMessage = $errorMessageTemplate . "Error convertation timeout";
				break;
			case -1:
				$errorMessage = $errorMessageTemplate . "Error convertation unknown";
				break;
			case 0:
				break;
			default:
				$errorMessage = $errorMessageTemplate . "ErrorCode = " . $errorCode;
				break;
		}

		throw new Exception($errorMessage);
	}

	protected function GenerateRevisionId($expected_key)
	{
		if (strlen($expected_key) > 20) $expected_key = crc32( $expected_key);
		$key = preg_replace("[^0-9-.a-zA-Z_=]", "_", $expected_key);
		$key = substr($key, 0, min(array(strlen($key), 20)));
		return $key;
	}

	public function EntryCallback()
	{
		$result = ["error" => 0];

		if (($body_stream = file_get_contents("php://input")) === FALSE) {
			$result["error"] = "Bad Request";
		} else {
			$data = json_decode($body_stream, TRUE);

			$oJwt = new Classes\JwtManager($this->getConfig('Secret', ''));
			if ($oJwt->isJwtEnabled()) {
				$inHeader = false;
				$token = "";
				if (!empty($data["token"])) {
					$token = $oJwt->jwtDecode($data["token"]);
				} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
					$token = $oJwt->jwtDecode(substr($_SERVER['HTTP_AUTHORIZATION'], strlen("Bearer ")));
					$inHeader = true;
				} else {
					$result["error"] = "Expected JWT";
				}
				if (empty($token)) {
					$result["error"] = "Invalid JWT signature";
				} else {
					$data = json_decode($token, true);
					if ($inHeader) $data = $data["payload"];
				}
			}

			if ($data["status"] == 2) {
				$sHash = (string) \Aurora\System\Router::getItemByIndex(1, '');
				if (!empty($sHash)) {
					$aHashValues = Api::DecodeKeyValues($sHash);

					$prevState = Api::skipCheckUserRole(true);
					$oFileInfo = FilesModule::Decorator()->GetFileInfo(
						$aHashValues['UserId'],
						$aHashValues['Type'],
						$aHashValues['Path'],
						$aHashValues['Name']
					);
					if ($oFileInfo instanceof FileItem && $this->isOfficeDocument($oFileInfo->Name)) {
						if ((isset($oFileInfo->ExtendedProps['SharedWithMeAccess']) && (int) $oFileInfo->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Write) || !isset($oFileInfo->ExtendedProps['SharedWithMeAccess'])
							&& !$this->isReadOnlyDocument($oFileInfo->Name)) {
							$rData = \file_get_contents($data["url"]);
							if ($rData !== false) {
								if ($this->getConfig('EnableHistory', false)) {							
									$histDir = $this->getHistoryDir(
										$oFileInfo->TypeStr, 
										$oFileInfo->Path, 
										$oFileInfo->Name,
										true
									);
									if ($histDir) {
										$curVer = $histDir->getFileVersion();
										$verDir = $histDir->getVersionDir($curVer + 1, true);
										$ext = strtolower(pathinfo($oFileInfo->Name, PATHINFO_EXTENSION));

										// save previous version
										Api::skipCheckUserRole(true);

										list(, $sOwnerUserPublicId) = split($verDir->getOwner());
										$iOwnerUserId = Api::getUserIdByPublicId($sOwnerUserPublicId);

										if (!isset($oFileInfo->ExtendedProps['Created']) && $curVer == 0) {
											FilesModule::Decorator()->UpdateExtendedProps(
												$iOwnerUserId,
												$aHashValues['Type'],
												$oFileInfo->Path,
												$oFileInfo->Name,
												[
													'Created' => $oFileInfo->LastModified
												]
											);
										}
										$GLOBALS['__SKIP_HISTORY__'] = true;

										FilesModule::Decorator()->Copy(
											$iOwnerUserId,
											$aHashValues['Type'],
											$aHashValues['Type'],
											$oFileInfo->Path,
											$verDir->getRelativePath() . '/' . $verDir->getName(),
											[
												[
													'FromType' => $aHashValues['Type'],
													'ToType' => $aHashValues['Type'],
													'FromPath' => $oFileInfo->Path,
													'ToPath' => $verDir->getRelativePath() . '/' . $verDir->getName(),
													'Name' => $oFileInfo->Name,
													'NewName' => 'prev.' . $ext
												]
											]
										);
										unset($GLOBALS['__SKIP_HISTORY__']);
									}
								}

								$mResult = $this->createFile(
									$iOwnerUserId, 
									$aHashValues['Type'], 
									$aHashValues['Path'], 
									$aHashValues['Name'], 
									$rData
								);

								if ($mResult !== false && $this->getConfig('EnableHistory', false)) {
									$this->updateHistory(
										$iOwnerUserId, 
										$aHashValues['Type'], 
										$verDir->getRelativePath() . '/' . $verDir->getName(), 
										$data
									);
								}
							}
						}
					}
					Api::skipCheckUserRole($prevState);
				}
			}
		}
		return json_encode($result);
	}

	/**
	 *
	 * @param type $aArguments
	 * @param type $aResult
	 */
	public function onGetFile(&$aArguments, &$aResult)
	{
		if ($this->isOfficeDocument($aArguments['Name'])) {
			$aArguments['NoRedirect'] = true;
		}
	}

		/**
	 * Writes to $aData variable list of DropBox files if $aData['Type'] is DropBox account type.
	 *
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onGetItems($aArgs, &$mResult)
	{
		if (is_array($mResult)) {
			foreach ($mResult as $oItem) {
				if ($oItem instanceof FileItem && $this->isOfficeDocument($oItem->Name)) {
					$bEncrypted = isset($oItem->ExtendedProps['InitializationVector']);
					$bAccessSet = isset($oItem->ExtendedProps['SharedWithMeAccess']);
					$bHasWriteAccess = !$bAccessSet || ($bAccessSet && ((int) $oItem->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Write || (int) $oItem->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Reshare));
					if (!$bEncrypted && $bHasWriteAccess) {
						if ($this->documentCanBeConverted($oItem->Name)) {
							$oItem->UnshiftAction([
								'convert' => [
									'url' => ''
								]
							]);
						} else if ($this->documentCanBeEdited($oItem->Name)) {
							$sHash = $oItem->getHash();
							$aHashValues = Api::DecodeKeyValues($sHash);
							$aHashValues['Edit'] = true;
							$sHash = Api::EncodeKeyValues($aHashValues);
							$oItem->UnshiftAction([
								'edit' => [
									'url' => '?download-file/' . $sHash .'/view'
								]
							]);
						}
					}
				}
			}
		}
	}

	public function onAfterGetFileInfo($aArgs, &$mResult)
	{
		if ($mResult) {
			if ($mResult instanceof FileItem && $this->isOfficeDocument($mResult->Name)) {
				if ((isset($mResult->ExtendedProps['SharedWithMeAccess']) && ((int) $mResult->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Write || (int) $mResult->ExtendedProps['SharedWithMeAccess'] === \Afterlogic\DAV\FS\Permission::Reshare)) || !isset($mResult->ExtendedProps['SharedWithMeAccess'])
					&& !$this->isReadOnlyDocument($mResult->Name)) {
					$sHash = $mResult->getHash();
					$aHashValues = Api::DecodeKeyValues($sHash);
					$aHashValues['Edit'] = true;
					$sHash = Api::EncodeKeyValues($aHashValues);
					$mResult->UnshiftAction([
						'edit' => [
							'url' => '?download-file/' . $sHash .'/view'
						]
					]);
				}
			}
		}
	}

	public function onAddToContentSecurityPolicyDefault($aArgs, &$aAddDefault)
	{
		$sUrl = $this->getConfig('DocumentServerUrl' , null);
		if (!empty($sUrl)) {
			$aAddDefault[] = $sUrl;
		}
	}


	// History
	public function RestoreFromHistory($Url, $Version)
	{

	}

	protected function getHistoryDir($sType, $sPath, $sName, $bCreateIfNotExists = false)
	{
		$oHistNode = false;
		/**
		 * @var $oNode
		 */
		$oNode = Server::getNodeForPath('files/' . $sType . $sPath . '/' . $sName);
		if ($oNode instanceof File) {
			$oHistNode = $oNode->getHistoryDirectory();
			if (!$oHistNode && $bCreateIfNotExists) {
				$oParentNode = Server::getNodeForPath('files/' . $sType . $sPath);
				$oParentNode->createDirectory($sName . '.hist');
				$oHistNode = $oNode->getHistoryDirectory();
			}
		}

		return $oHistNode;
	}

	protected function getHistory($oFileInfo, $docKey, $sUrl)
	{
		$result = [];
		$curVer = 0;
		$histDir = $this->getHistoryDir($oFileInfo->TypeStr, $oFileInfo->Path, $oFileInfo->Name);
		if ($histDir) {
			$curVer = $histDir->getFileVersion();
		}

		if ($curVer > 0) {
			$hist = [];
			$histData = [];
			$oUser = CoreModule::getInstance()->GetUserByPublicId($oFileInfo->Owner);

			for ($i = 0; $i <= $curVer; $i++) {
				$obj = [];
				$dataObj = [];

				$verDir = $histDir->getVersionDir($i + 1);

				$key = false;

				if ($i == $curVer) {
					$key = $docKey;
				} else {
					if ($verDir && $verDir->childExists('key.txt')) {
						$oKeyFile = $verDir->getChild('key.txt');
						if ($oKeyFile instanceof \Afterlogic\DAV\FS\File) {
							$mKeyData = $oKeyFile->get(false);
							if (is_resource($mKeyData)) {
								$key = \stream_get_contents($mKeyData);
							}
						}
					} else {
						$key = false;
					}
				}

				if ($key === false) {
					continue;
				}
				$obj["key"] = $key;
				$obj["version"] = $i + 1;

				if ($i === 0) {
					$ext = strtolower(pathinfo($oFileInfo->Name, PATHINFO_EXTENSION));
					list(, $sUserPublicId) = split($verDir->getOwner());
					$prevState = Api::skipCheckUserRole(true);
					$aPrevFileExtendedProps = FilesModule::Decorator()->GetExtendedProps(
						$sUserPublicId,
						$oFileInfo->TypeStr,
						$verDir->getRelativePath() . '/' . $verDir->getName(),
						'prev.' . $ext
					);
					Api::skipCheckUserRole($prevState);
					if (isset($aPrevFileExtendedProps['Created'])) {
						$obj["created"] =  $this->convetToUserTime(
							$oUser,
							date("Y-m-d H:i:s", $aPrevFileExtendedProps['Created'])
						);
					} else {
						$obj["created"] = '';
					}
					$obj["user"] = [
						"id" => (string) $oUser->Id,
						"name" => $oFileInfo->Owner
					];
				}

				if ($i != $curVer) {
					$ext = strtolower(pathinfo($oFileInfo->Name, PATHINFO_EXTENSION));

					$sUrl = $this->getDownloadUrl(
						Api::getUserIdByPublicId($sUserPublicId), 
						$oFileInfo->TypeStr, 
						$verDir->getRelativePath() . '/' . $verDir->getName(), 
						'prev.' . $ext
					);
				}

				$dataObj["key"] = $key;
				$dataObj["url"] = $sUrl;
				$dataObj["version"] = $i + 1;

				if ($i > 0) {
					$changes = false;
					$verDirPrev = $histDir->getVersionDir($i);
					if ($verDirPrev && $verDirPrev->childExists('changes.json')) {
						$oChangesFile = $verDirPrev->getChild('changes.json');
						if ($oChangesFile instanceof \Afterlogic\DAV\FS\File) {
							$mChangesFileData = $oChangesFile->get(false);
							if (is_resource($mChangesFileData)) {
								$changes = \json_decode(\stream_get_contents($mChangesFileData), true);
							}
						}
					}

					if (!$changes) {
						continue;
					}
					$change = $changes["changes"][0];

					$obj["changes"] = $changes["changes"];
					$obj["serverVersion"] = $changes["serverVersion"];
					$obj["created"] = $this->convetToUserTime(
						$oUser,
						$change["created"]
					);
					$obj["user"] = $change["user"];

					if (isset($histData[$i])) {
						$prev = $histData[$i];
						$dataObj["previous"] = [
							"key" => $prev["key"],
							"url" => $prev["url"]
						];
					}

					$dataObj["changesUrl"] = $this->getDownloadUrl(
						Api::getUserIdByPublicId($sUserPublicId), 
						$oFileInfo->TypeStr, 
						$histDir->getVersionDir($i)->getRelativePath() . '/' . $verDirPrev->getName(), 'diff.zip'
					);
				}

				array_push($hist, $obj);
				$oJwt = new Classes\JwtManager($this->getConfig('Secret', ''));
				if ($oJwt->isJwtEnabled()) {
					$dataObj['token'] = $oJwt->jwtEncode($dataObj);
				}
				$histData[$i + 1] = $dataObj;
			}

			array_push($result, [
					"currentVersion" => $curVer + 1,
					"history" => $hist
				],
				$histData
			);
		}

		return $result;
	}

	protected function updateHistory($iUserId, $sType, $sPath, $data)
	{
		$rChangesData = \file_get_contents($data["changesurl"]);
		if ($rChangesData !== false) {
			$this->createFile($iUserId, $sType, $sPath, 'diff.zip', $rChangesData);
		}

		$histData = isset($data["changeshistory"]) ? $data["changeshistory"] : '';
		if (empty($histData)) {
		 	$histData = json_encode($data["history"], JSON_PRETTY_PRINT);
		}
		if (!empty($histData)) {
			$this->createFile($iUserId, $sType, $sPath, 'changes.json', $histData);
		}
		$this->createFile($iUserId, $sType, $sPath, 'key.txt', $data['key']);
	}

	protected function createFile($iUserId, $sType, $sPath, $sFileName, $mData)
	{
		$mResult = false;
		$aArgs = [
			'UserId' => $iUserId,
			'Type' => $sType,
			'Path' => $sPath,
			'Name' => $sFileName,
			'Data' => $mData,
			'Overwrite' => true,
			'RangeType' => 0,
			'Offset' => 0,
			'ExtendedProps' => []
		];

		$this->broadcastEvent(
			'Files::CreateFile',
			$aArgs,
			$mResult
		);

		return $mResult;
	}

	protected function getDownloadUrl($iUserId, $sType, $sPath, $sName)
	{
		$sHash = Api::EncodeKeyValues([
			'UserId' =>  $iUserId,
			'Id' => $sName,
			'Type' => $sType,
			'Path' => $sPath,
			'Name' => $sName,
			'FileName' => $sName,
			'AuthToken' => Api::UserSession()->Set([
				'token' => 'auth',
				'id' => $iUserId
			])	
		]);

		return Application::getBaseUrl() . '?download-file/' . $sHash;
	}

	protected function convetToUserTime($oUser, $sTime)
	{
		$dt = \DateTime::createFromFormat(
			'Y-m-d H:i:s',
			$sTime,
			new \DateTimeZone('UTC')
		);
		$dt->setTimezone(new \DateTimeZone($oUser->DefaultTimeZone));

		return $dt->format("Y-m-d H:i:s");
	}
}
