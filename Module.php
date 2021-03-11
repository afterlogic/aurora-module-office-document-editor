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
		$this->AddEntries(array(
			'editor' => 'EntryEditor',
			'ode-callback' => 'EntryCallback'
		)
	);
		$this->subscribeEvent('System::RunEntry::before', array($this, 'onBeforeFileViewEntry'));
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'), 10);
	}

	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return array(
			'ExtensionsToView' => $this->getExtensionsToView()
		);
	}

	protected function getExtensionsToView()
	{
		return $this->getConfig('ExtensionsToView', [
			'doc',
			'docx',
			'docm',
			'dotm',
			'dotx',
			'xlsx',
			'xlsb',
			'xls',
			'xlsm',
			'pptx',
			'ppsx',
			'ppt',
			'pps',
			'pptm',
			'potm',
			'ppam',
			'potx',
			'ppsm',
			'odt',
			'odx']
		);
	}

	protected function getDocumentType($filename)
	{
		$ext = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));

		if (in_array($ext, $this->ExtsDocument)) return "text";
		if (in_array($ext, $this->ExtsSpreadsheet)) return "spreadsheet";
		if (in_array($ext, $this->ExtsPresentation)) return "presentation";
		return "";
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

			if ($this->isOfficeDocument($sFileName) && $sAction === 'view')
			{
				if (!isset($aValues['AuthToken']))
				{
					$aValues['AuthToken'] = \Aurora\System\Api::UserSession()->Set(
						array(
							'token' => 'auth',
							'id' => \Aurora\System\Api::getAuthenticatedUserId()
						),
						time(),
						time() + 60 * 5 // 5 min
					);

					$sHash = \Aurora\System\Api::EncodeKeyValues($aValues);

					// 'https://view.officeapps.live.com/op/view.aspx?src=';
					// 'https://view.officeapps.live.com/op/embed.aspx?src=';
					// 'https://docs.google.com/viewer?embedded=true&url=';

					$sViewerUrl = './?editor&filename='. $sFileName .'&fileuri=';
					if (!empty($sViewerUrl))
					{
						\header('Location: ' . $sViewerUrl . urlencode($_SERVER['HTTP_REFERER'] . '?' . $sEntry .'/' . $sHash . '/' . $sAction));
					}
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
		$fileuri = isset($_GET['fileuri']) ? $_GET['fileuri'] : null;
		$filename = isset($_GET['filename']) ? $_GET['filename'] : null;
		$filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$docKey = '';
		$lang = 'en';
		$mode = 'edit';
		$fileuriUser = '';
		$serverPath = 'https://oo.afterlogic.com:8088';
		$callbackUrl = $this->oHttp->GetFullUrl() . '?ode-callback';

		if (isset($fileuri))
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser)
			{
				$uid = $oUser->EntityId;
				$uname = $oUser->Name;
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
						"author" => "Me",
						"created" => date('d.m.y')
					],
					"permissions" => [
						"comment" => true,
						"download" => true,
						"edit" => true,
						"fillForms" => true,
						"modifyFilter" => true,
						"modifyContentControl" => true,
						"review" => true
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
						"about" => true,
						"feedback" => true,
						"goback" => [
							"url" => $serverPath,
						]
					]
				]
			];

			$sResult = \file_get_contents($this->GetPath().'/templates/Editor.html');

			$oApiIntegrator = \Aurora\System\Managers\Integrator::getInstance();
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
			if (0 < $iUserId)
			{
				$sResult = strtr($sResult, [
					'{{DOC_SERV_API_URL}}' => $serverPath . '/web-apps/apps/api/documents/api.js',
					'{{FILENAME}}' => $filename,
					'{{FILETYPE}}' => $filetype,
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

	public function EntryCallback()
	{
		return "{\"error\":0}";
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
}
