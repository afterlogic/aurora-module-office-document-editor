<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\OfficeDocumentEditor;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{

	public $ExtsSpreadsheet = [".xls", ".xlsx", ".xlsm", ".xlt", ".xltx", ".xltm", ".ods", ".fods", ".ots", ".csv"];

	public $ExtsPresentation = [".pps", ".ppsx", ".ppsm", ".ppt", ".pptx", ".pptm", ".pot", ".potx", ".potm", ".odp", ".fodp", ".otp"];

	public $ExtsDocument = [".doc", ".docx", ".docm", ".dot", ".dotx", ".dotm", ".odt", ".fodt", ".ott", ".rtf", ".txt", ".html", ".htm", ".mht", ".pdf", ".djvu", ".fb2", ".epub", ".xps"];

	public $ExtsReadOnly = [".xls", ".pps", ".doc", ".odt"];

	/**
	 * Initializes module.
	 *
	 * @ignore
	 */
	public function init()
	{
		$this->AddEntries([
			'editor' => 'EntryEditor',
			'ode-callback' => 'EntryCallback'
		]);

		$this->subscribeEvent('System::RunEntry::before', [$this, 'onBeforeFileViewEntry']);
		$this->subscribeEvent('Files::GetFile', [$this, 'onGetFile'], 10);
		$this->subscribeEvent('Files::GetItems::after', array($this, 'onAfterGetItems'), 20000);
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'onAfterGetFileInfo'), 20000);
	}

	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return [
			'ExtensionsToView' => $this->getExtensionsToView()
		];
	}

	protected function getExtensionsToView()
	{
		$aExtensions = array_merge($this->ExtsSpreadsheet, $this->ExtsPresentation, $this->ExtsDocument);
		return $this->getConfig('ExtensionsToView', $aExtensions);
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
		$ext = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));

		return in_array($ext, $this->ExtsReadOnly);
	}

	/**
	 * @param string $sFileName = ''
	 * @return bool
	 */
	protected function isOfficeDocument($sFileName = '')
	{
		$sExtensions = implode('|', $this->getExtensionsToView());
		return !!preg_match('/\.(' . $sExtensions . ')$/', strtolower(trim($sFileName)));
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
		if (in_array($aArguments['EntryName'], $aEntries))
		{
			$sEntry = (string) \Aurora\System\Router::getItemByIndex(0, '');
			$sHash = (string) \Aurora\System\Router::getItemByIndex(1, '');
			$sAction = (string) \Aurora\System\Router::getItemByIndex(2, '');

			$aValues = \Aurora\System\Api::DecodeKeyValues($sHash);

			$sFileName = isset($aValues['FileName']) ? urldecode($aValues['FileName']) : '';
			if (empty($sFileName))
			{
				$sFileName = isset($aValues['Name']) ? urldecode($aValues['Name']) : '';
			}

			if ($this->isOfficeDocument($sFileName))
			{
				if (!isset($aValues['AuthToken']))
				{
					$aValues['AuthToken'] = \Aurora\System\Api::UserSession()->Set(
						[
							'token' => 'auth',
							'id' => \Aurora\System\Api::getAuthenticatedUserId()
						],
						time(),
						time() + 60 * 5 // 5 min
					);

					$sHash = \Aurora\System\Api::EncodeKeyValues($aValues);

					$sViewerUrl = './?editor=' . urlencode($sEntry .'/' . $sHash . '/' . $sAction);
					\header('Location: ' . $sViewerUrl);
				}
				else
				{
					$sAuthToken = isset($aValues['AuthToken']) ? $aValues['AuthToken'] : null;
					if (isset($sAuthToken))
					{
						\Aurora\System\Api::setAuthToken($sAuthToken);
						\Aurora\System\Api::setUserId(
							\Aurora\System\Api::getAuthenticatedUserId($sAuthToken)
						);
					}
				}
			}
		}
	}

	public function EntryEditor()
	{
		$sResult = '';
		$sFullUrl = $this->oHttp->GetFullUrl();
		$sMode = 'view';
		$fileuri = isset($_GET['editor']) ? $_GET['editor'] : null;
		$filename = null;
		$sHash = null;
		$aHashValues = [];
		$docKey = null;
		$lastModified = time();
		if (isset($fileuri))
		{
			$fileuri = \urldecode($fileuri);
			$aFileuri = \explode('/', $fileuri);
			if (isset($aFileuri[1]))
			{
				$sHash = $aFileuri[1];
			}
			$fileuri = $sFullUrl . '?' . $fileuri;
		}

		if (isset($sHash))
		{
			$aHashValues = \Aurora\System\Api::DecodeKeyValues($sHash);
			if (isset($aHashValues['FileName']))
			{
				$filename = $aHashValues['FileName'];
			}
			else if (isset($aHashValues['Name']))
			{
				$filename = $aHashValues['Name'];
			}
			if (isset($aHashValues['Edit']))
			{
				$sMode = 'edit';
			}

			$sHash = \Aurora\System\Api::EncodeKeyValues($aHashValues);

			$oFileInfo = \Aurora\Modules\Files\Module::Decorator()->GetFileInfo(
				$aHashValues['UserId'],
				$aHashValues['Type'],
				$aHashValues['Path'],
				$aHashValues['Id']
			);
			if ($oFileInfo)
			{
				$lastModified = $oFileInfo->LastModified;
				$docKey = \md5($oFileInfo->RealPath . $lastModified);
			}
		}

		$bIsReadOnlyMode = ($sMode === 'view') ? true : false;

		$filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$lang = 'en';
		$mode = $bIsReadOnlyMode || $this->isReadOnlyDocument($filename) ? 'view' : 'edit';
		$fileuriUser = '';

		$serverPath = $this->getConfig('DocumentServerUrl' , null);

		$callbackUrl = $sFullUrl . '?ode-callback/' . $sHash;

		if (isset($fileuri) && isset($serverPath))
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser)
			{
				$uid = (string) $oUser->EntityId;
				$uname = !empty($oUser->Name) ? $oUser->Name : $oUser->PublicId;
				$lang = \Aurora\System\Utils::ConvertLanguageNameToShort($oUser->Language);
			}

			$config = [
				"type" => empty($_GET["type"]) ? "desktop" : $_GET["type"],
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
						"review" => !$bIsReadOnlyMode
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
			if ($oJwt->isJwtEnabled())
			{
				$config['token'] = $oJwt->jwtEncode($config);
			}

			$sResult = \file_get_contents($this->GetPath().'/templates/Editor.html');

			$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
			if (0 < $iUserId)
			{
				$sResult = strtr($sResult, [
					'{{DOC_SERV_API_URL}}' => $serverPath . '/web-apps/apps/api/documents/api.js',
					'{{CONFIG}}' => \json_encode($config)
				]);
				\Aurora\Modules\CoreWebclient\Module::Decorator()->SetHtmlOutputHeaders();
			}
			else
			{
				\Aurora\System\Api::Location('./');
			}
		}

		return $sResult;
	}

	public function CreateBlankDocument($Type, $Path, $FileName)
	{
		$mResult = false;
		$ext = strtolower(pathinfo($FileName, PATHINFO_EXTENSION));
		$sFilePath = $this->GetPath() . "/data/new." . $ext;
		if (file_exists($sFilePath))
		{
			$rData = \fopen($sFilePath , "r");
			$FileName = \Aurora\Modules\Files\Module::Decorator()->GetNonExistentFileName(
				\Aurora\System\Api::getAuthenticatedUserId(),
				$Type,
				$Path,
				$FileName
			);
			$aArgs = [
				'UserId' => \Aurora\System\Api::getAuthenticatedUserId(),
				'Type' => $Type,
				'Path' => $Path,
				'Name' => $FileName,
				'Data' => $rData,
				'Overwrite' => false,
				'RangeType' => 0,
				'Offset' => 0,
				'ExtendedProps' => []
			];
			$this->broadcastEvent(
				'Files::CreateFile',
				$aArgs,
				$mResult
			);

			if ($mResult)
			{
				$mResult = \Aurora\Modules\Files\Module::Decorator()->GetFileInfo(\Aurora\System\Api::getAuthenticatedUserId(), $Type, $Path, $FileName);
			}
		}
		return $mResult;
	}

	public function EntryCallback()
	{
		$result = ["error" => 0];

		if (($body_stream = file_get_contents("php://input")) === FALSE)
		{
			$result["error"] = "Bad Request";
		}
		else
		{
			$data = json_decode($body_stream, TRUE);

			$oJwt = new Classes\JwtManager($this->getConfig('Secret', ''));
			if ($oJwt->isJwtEnabled())
			{
				$inHeader = false;
				$token = "";
				if (!empty($data["token"]))
				{
					$token = $oJwt->jwtDecode($data["token"]);
				}
				elseif (!empty($_SERVER['HTTP_AUTHORIZATION']))
				{
					$token = $oJwt->jwtDecode(substr($_SERVER['HTTP_AUTHORIZATION'], strlen("Bearer ")));
					$inHeader = true;
				}
				else
				{
					$result["error"] = "Expected JWT";
				}
				if (empty($token))
				{
					$result["error"] = "Invalid JWT signature";
				}
				else
				{
					$data = json_decode($token, true);
					if ($inHeader) $data = $data["payload"];
				}
			}

			if ($data["status"] == 2)
			{
				$sHash = (string) \Aurora\System\Router::getItemByIndex(1, '');
				if (!empty($sHash))
				{
					$aHashValues = \Aurora\System\Api::DecodeKeyValues($sHash);

					$aHashValues['UserId'];
					$rData = \fopen($data["url"], "r");

					$aArgs = [
						'UserId' => (int) $aHashValues['UserId'],
						'Type' => $aHashValues['Type'],
						'Path' => $aHashValues['Path'],
						'Name' => $aHashValues['Name'],
						'Data' => $rData,
						'Overwrite' => true,
						'RangeType' => 0,
						'Offset' => 0,
						'ExtendedProps' => []
					];
					\Aurora\System\Api::skipCheckUserRole(true);
					$this->broadcastEvent(
						'Files::CreateFile',
						$aArgs,
						$mResult
					);
					\Aurora\System\Api::skipCheckUserRole(false);
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
		if ($this->isOfficeDocument($aArguments['Name']))
		{
			$aArguments['NoRedirect'] = true;
		}
	}

		/**
	 * Writes to $aData variable list of DropBox files if $aData['Type'] is DropBox account type.
	 *
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterGetItems($aArgs, &$mResult)
	{
		if (is_array($mResult))
		{
			foreach ($mResult as $oItem)
			{
				if ($oItem instanceof \Aurora\Modules\Files\Classes\FileItem && $this->isOfficeDocument($oItem->Name))
				{
					if ((isset($oItem->ExtendedProps['Access']) && (int) $oItem->ExtendedProps['Access'] === \Afterlogic\DAV\FS\Permission::Write) || !isset($oItem->ExtendedProps['Access'])
						&& !$this->isReadOnlyDocument($oItem->Name))
					{
						$sHash = $oItem->getHash();
						$aHashValues = \Aurora\System\Api::DecodeKeyValues($sHash);
						$aHashValues['Edit'] = true;
						$sHash = \Aurora\System\Api::EncodeKeyValues($aHashValues);
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

	public function onAfterGetFileInfo($aArgs, &$mResult)
	{
		if ($mResult)
		{
			if ($mResult instanceof \Aurora\Modules\Files\Classes\FileItem && $this->isOfficeDocument($mResult->Name))
			{
				if ((isset($mResult->ExtendedProps['Access']) && (int) $mResult->ExtendedProps['Access'] === \Afterlogic\DAV\FS\Permission::Write) || !isset($mResult->ExtendedProps['Access'])
					&& !$this->isReadOnlyDocument($mResult->Name))
				{
					$sHash = $mResult->getHash();
					$aHashValues = \Aurora\System\Api::DecodeKeyValues($sHash);
					$aHashValues['Edit'] = true;
					$sHash = \Aurora\System\Api::EncodeKeyValues($aHashValues);
					$mResult->UnshiftAction([
						'edit' => [
							'url' => '?download-file/' . $sHash .'/view'
						]
					]);
				}
			}
		}
	}
}
