<?php

namespace App\Http\Controllers;

use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\FileLinksCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Filters\CatalogElementsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFieldsValues\FileCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\FileCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\FileCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\FileLinkModel;
use AmoCRM\Models\Files\FileUploadModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\AttachmentNote;
use AmoCRM\Models\TaskModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Client\AmoCRMApiClientException;
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use League\OAuth2\Client\Token\AccessToken;

class AmoController extends Controller
{
    //  private $accessToken;
    public function index()
    {
        $clientId = env('AMOCRM_CLIENT_ID');
        $clientSecret = env('AMOCRM_CLIENT_SECRET');
        $redirectUri = env('AMOCRM_REDIRECT_URI');

        $apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        return view('amo.index', [
            'apiClient' => $apiClient
        ]);
    }

    public function create(Request $request)
    {
        $apiClient = new \AmoCRM\Client\AmoCRMApiClient(env('AMOCRM_CLIENT_ID'), env('AMOCRM_CLIENT_SECRET'), env('AMOCRM_REDIRECT_URI'));
        if (isset($_GET['code'])) {
            $auth = $_GET['code'];
            try {
                $oauth = $apiClient->getOAuthClient();
                $oauth->setBaseDomain("ankulagin.amocrm.ru");
                $accessToken = $oauth->getAccessTokenByCode($auth);  //$this->accessToken
                $request->session()->put('amo_access_token', $accessToken);
            } catch (\AmoCRM\Exceptions\AmoCRMoAuthApiException $e) {
                echo $e->getMessage();
            }

            // dd($this->accessToken);
        }
        return view('amo.create');
    }

    public function store(Request $request)
    {

        $data = json_decode($request->getContent(), true);
        $apiClient = new AmoCRMApiClient(env('AMOCRM_CLIENT_ID'), env('AMOCRM_CLIENT_SECRET'), env('AMOCRM_REDIRECT_URI'));
        $accessToken = $request->session()->get('amo_access_token');
        $validator = Validator::make($request->json()->all(), [
            'name' => 'required|string|max:30',
            'surname' => 'required|string|max:30',
            'age' => 'required|integer|min:18|max:100',
            'gender' => 'required|in:male,female',
            'phone' => 'required|string|min:11|max:20',
            'email' => 'required|email|max:255',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // Если данные валидны, выполняем вашу логику обработки данных
        $apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain("ankulagin.amocrm.ru")
            ->onAccessTokenRefresh(
                function (AccessTokenInterface $accessToken, string $baseDomain) {
                    saveToken(
                        [
                            'accessToken' => $accessToken->getToken(),
                            'refreshToken' => $accessToken->getRefreshToken(),
                            'expires' => $accessToken->getExpires(),
                            'baseDomain' => $baseDomain,
                        ]
                    );
                }
            );
        /*
         * CUSTOMER AND TESTING
         */
        try {

            $leadsService = $apiClient->leads();
            $phoneToCheck = $request->json('phone');
            $allContact = $apiClient->contacts()->get(with:['leads'])->all();
            foreach ($allContact as $contact) {
                if ($contact != null) {
                    if ($contact->getCustomFieldsValues()->getBy('fieldCode', 'PHONE')->getValues()->first()->getValue('value') == $phoneToCheck) {
                        if ($contact->getLeads() != null) {
                            foreach ($contact->getLeads() as $lead) {
                                $leadId = $lead->getId();
                                if ($leadsService->getOne($leadId)->getStatusId() == LeadModel::WON_STATUS_ID) {
                                    //todo file start
                                    /*$files = $apiClient->files()->get();
                                    $uploadModel = new FileUploadModel();
                                    $uploadModel->setName('Фото')
                                        ->setLocalPath('/home/ankul/PhpstormProjects/amocrmlaravel/testphotoamo.png');

                                    try {
                                        $file = $apiClient->files()->uploadOne($uploadModel);
                                    } catch (AmoCRMApiException $e) {
                                        printError($e);
                                    }*/
                                    //todo file end
                                    $customer = new CustomerModel();
                                    $customer->setName('Созданный покупатель ');
                                    $contactsCollection = new \AmoCRM\Collections\ContactsCollection();
                                    $contactsCollection->add($contact);
                                    $customersService = $apiClient->customers();

                                    try {
                                        $customer = $customersService->addOne($customer);
                                        $linksCollection = new LinksCollection();
                                        $linksCollection->add($contact);
                                        $linksCollection = $apiClient->customers()->link($customer, $linksCollection);
                                        //todo notes
                                        $file = $apiClient->files()->get()->first();
                                        //Создадим примечание с файлом
                                        $noteModel = new AttachmentNote();
                                        $noteModel->setEntityId($customer->getId())
                                            ->setFileName($file->getName() . '.png') // название файла, которое будет отображаться в примечании
                                            ->setVersionUuid($file->getVersionUuid())
                                            ->setFileUuid($file->getUuid());
                                        try {
                                            $leadNotesService = $apiClient->notes(EntityTypesInterface::CUSTOMERS);
                                            // dd($leadNotesService->get());
                                            $noteModel = $leadNotesService->addOne($noteModel);
                                        } catch (AmoCRMApiException $e) {
                                            echo $e->getMessage();
                                            $this->printError($e);
                                        }





                                        /* $result = $apiClient->entityFiles(EntityTypesInterface::CUSTOMERS, $customer->getId())->add(
                                            (new FileLinksCollection())
                                                ->add(
                                                    (new FileLinkModel())
                                                        ->setFileUuid($file->getUuid())
                                                )
                                        );*/

                                    } catch (AmoCRMApiException $e) {
                                        echo $e->getMessage();
                                        $this->printError($e);
                                    }
                                    return response()->json(['success' => true]);
                                }
                            }
                            return response()->json(['success' => true]);
                        }
                        return response()->json(['success' => true]);
                    }
                }
            }
            /*
* USER
*/
            $userinfo = $apiClient->users()->get();
            $randomKey = array_rand($userinfo->toArray());
            // Получение случайного пользователя
            $randomUser = $userinfo[$randomKey];
            // Получение ID случайного пользователя
            $randomUserId = $randomUser->getId();
            /*
                     * CONTACT
                     */
            $contact = new ContactModel();
            $contact->setResponsibleUserId($randomUserId);
            $contact->setName($request->json('name'));
            $contact->setFirstName($request->json('name'));
            $contact->setLastName($request->json('surname'));
            // Получение коллекции полей контакта
            $customFields = $contact->getCustomFieldsValues();
            // Проверка наличия коллекции и создание, если её нет
            if ($customFields === null) {
                $customFields = new CustomFieldsValuesCollection();
                $contact->setCustomFieldsValues($customFields);
            }
            /*
             * PHONE
             */
            // Проверка наличия поля 'PHONE'
            $phoneField = $customFields->getBy('fieldCode', 'PHONE') ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($phoneField)) {
                $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
                $customFields->add($phoneField);
            }

            // Установка значения поля
            $phoneField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($request->json('phone'))
                    )
            );
            /*
             * EMAIL
             */
            // Проверка наличия поля 'EMAIL'
            $emailField = $customFields->getBy('fieldCode', 'EMAIL') ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($emailField)) {
                $emailField = (new TextCustomFieldValuesModel())->setFieldCode('EMAIL');
                $customFields->add($emailField);
            }
            // Установка значения поля
            $emailField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('email'))
                    )
            );
            /*
             * AGE
             */
            // Проверка наличия поля 'Возвраст' по ID
            $ageField = $customFields->getBy('fieldId', 634311) ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($ageField)) {
                $ageField = (new TextCustomFieldValuesModel())->setFieldId(634311);
                $customFields->add($ageField);
            }
            // Установка значения поля
            $ageField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('age'))
                    )
            );
            /*
             * GENDER
             */
            // Проверка наличия поля 'Пол' по ID
            $genderField = $customFields->getBy('fieldId', 647239) ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($genderField)) {
                $genderField = (new TextCustomFieldValuesModel())->setFieldId(647239);
                $customFields->add($genderField);
            }
            // Установка значения поля
            $genderField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('gender'))
                    )
            );

            /*
                                        *ADD CONTACT
                                        */
            $contactModel = $apiClient->contacts()->addOne($contact);

            /*
          * LEADS
          */
            try {
                $leadsCollection = $leadsService->get();
                //$leadsCollection = $leadsService->nextPage($leadsCollection);
            } catch (AmoCRMApiException $e) {
                echo $e->getMessage();
                $this->printError($e);
            }
            $lead = new LeadModel();
            $lead->setName('Новая очень новая сделка')
                ->setPrice(54321)
                ->setContacts(
                    (new ContactsCollection())
                        ->add($contact));
            $leadsCollection = new LeadsCollection();
            $leadsCollection->add($lead);
            try {
                $leadsCollection = $leadsService->add($leadsCollection);

            } catch (AmoCRMApiException $e) {
                echo $e->getMessage();
                $this->printError($e);
            }
            /*
                     * TASK
                     */

            // Получение даты и времени сейчас
            $currentDateTime = Carbon::now();

            // Добавление 4 рабочих дней к текущей дате
            $dueDate = $currentDateTime->addWeekday(4);

            // Установка времени начала "рабочего дня" (9:00)
            $dueDate->setTime(9, 0, 0);

            // Проверка, является ли текущий день рабочим днем, и если нет, то перемещение на следующий рабочий день
            if ($dueDate->isWeekday()) {
                $tasksService = $apiClient->tasks();

                $tasksCollection = new TasksCollection();
                $task = new TaskModel();
                $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
                    ->setText('Новая задача')
                    ->setCompleteTill($dueDate->timestamp)
                    ->setEntityType(EntityTypesInterface::LEADS)
                    ->setEntityId($leadsCollection->first()->getId())
                    ->setDuration(30 * 60)// 30 минут
                    ->setResponsibleUserId($randomUserId);

                $tasksCollection->add($task);
                // Добавление задачи
                try {
                    $tasksCollection = $tasksService->add($tasksCollection);
                } catch (AmoCRMApiException $e) {
                    echo $e->getMessage();
                    $this->printError($e);
                }
            }
            /*
             * CATALOG ELEMENT LINK
             */
            $catalogsCollection = $apiClient->catalogs()->get();
            $catalog = $catalogsCollection->getBy('name', 'Товары');
            $catalogElementsCollection = new CatalogElementsCollection();
            $catalogElementsService = $apiClient->catalogElements($catalog->getId());
            $catalogElementsFilter = new CatalogElementsFilter();
            $catalogElementsFilter->setQuery('Кружка');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $capElement = $catalogElementsCollection->first();
            $capElement->setQuantity(10.22);
            $catalogElementsFilter->setQuery('Чайник');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $teapotElement = $catalogElementsCollection->first();
            $teapotElement->setQuantity(10.22);

            $links = new LinksCollection();
            $links->add($capElement);
            $links->add($teapotElement);
            $apiClient->leads()->link($lead, $links);
        } catch (AmoCRMApiException $e) {
            /*
  * USER
  */
            $userinfo = $apiClient->users()->get();
            $randomKey = array_rand($userinfo->toArray());
            // Получение случайного пользователя
            $randomUser = $userinfo[$randomKey];
            // Получение ID случайного пользователя
            $randomUserId = $randomUser->getId();
            /*
                     * CONTACT
                     */
            $contact = new ContactModel();
            $contact->setResponsibleUserId($randomUserId);
            $contact->setName($request->json('name'));
            $contact->setFirstName($request->json('name'));
            $contact->setLastName($request->json('surname'));
            // Получение коллекции полей контакта
            $customFields = $contact->getCustomFieldsValues();
            // Проверка наличия коллекции и создание, если её нет
            if ($customFields === null) {
                $customFields = new CustomFieldsValuesCollection();
                $contact->setCustomFieldsValues($customFields);
            }
            /*
             * PHONE
             */
            // Проверка наличия поля 'PHONE'
            $phoneField = $customFields->getBy('fieldCode', 'PHONE') ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($phoneField)) {
                $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
                $customFields->add($phoneField);
            }

            // Установка значения поля
            $phoneField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($request->json('phone'))
                    )
            );
            /*
             * EMAIL
             */
            // Проверка наличия поля 'EMAIL'
            $emailField = $customFields->getBy('fieldCode', 'EMAIL') ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($emailField)) {
                $emailField = (new TextCustomFieldValuesModel())->setFieldCode('EMAIL');
                $customFields->add($emailField);
            }
            // Установка значения поля
            $emailField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('email'))
                    )
            );
            /*
             * AGE
             */
            // Проверка наличия поля 'Возвраст' по ID
            $ageField = $customFields->getBy('fieldId', 634311) ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($ageField)) {
                $ageField = (new TextCustomFieldValuesModel())->setFieldId(634311);
                $customFields->add($ageField);
            }
            // Установка значения поля
            $ageField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('age'))
                    )
            );
            /*
             * GENDER
             */
            // Проверка наличия поля 'Пол' по ID
            $genderField = $customFields->getBy('fieldId', 647239) ?? null;
            // Если значения нет, то создание нового объекта поля и добавление его в коллекцию значений
            if (empty($genderField)) {
                $genderField = (new TextCustomFieldValuesModel())->setFieldId(647239);
                $customFields->add($genderField);
            }
            // Установка значения поля
            $genderField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('gender'))
                    )
            );

            /*
                                        *ADD CONTACT
                                        */
            $contactModel = $apiClient->contacts()->addOne($contact);

            /*
          * LEADS
          */
            try {
                $leadsCollection = $leadsService->get();
                //$leadsCollection = $leadsService->nextPage($leadsCollection);
            } catch (AmoCRMApiException $e) {
                echo $e->getMessage();
                $this->printError($e);
            }
            $lead = new LeadModel();
            $lead->setName('Новая очень новая сделка')
                ->setPrice(54321)
                ->setContacts(
                    (new ContactsCollection())
                        ->add($contact));
            $leadsCollection = new LeadsCollection();
            $leadsCollection->add($lead);
            try {
                $leadsCollection = $leadsService->add($leadsCollection);

            } catch (AmoCRMApiException $e) {
                echo $e->getMessage();
                $this->printError($e);
            }
            /*
                     * TASK
                     */

            // Получение даты и времени сейчас
            $currentDateTime = Carbon::now();

            // Добавление 4 рабочих дней к текущей дате
            $dueDate = $currentDateTime->addWeekday(4);

            // Установка времени начала "рабочего дня" (9:00)
            $dueDate->setTime(9, 0, 0);

            // Проверка, является ли текущий день рабочим днем, и если нет, то перемещение на следующий рабочий день
            if ($dueDate->isWeekday()) {
                $tasksService = $apiClient->tasks();

                $tasksCollection = new TasksCollection();
                $task = new TaskModel();
                $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
                    ->setText('Новая задача')
                    ->setCompleteTill($dueDate->timestamp)
                    ->setEntityType(EntityTypesInterface::LEADS)
                    ->setEntityId($leadsCollection->first()->getId())
                    ->setDuration(30 * 60)// 30 минут
                    ->setResponsibleUserId($randomUserId);

                $tasksCollection->add($task);
                // Добавление задачи
                try {
                    $tasksCollection = $tasksService->add($tasksCollection);
                } catch (AmoCRMApiException $e) {
                    echo $e->getMessage();
                    $this->printError($e);
                }
            }
            /*
             * CATALOG ELEMENT LINK
             */
            $catalogsCollection = $apiClient->catalogs()->get();
            $catalog = $catalogsCollection->getBy('name', 'Товары');
            $catalogElementsCollection = new CatalogElementsCollection();
            $catalogElementsService = $apiClient->catalogElements($catalog->getId());
            $catalogElementsFilter = new CatalogElementsFilter();
            $catalogElementsFilter->setQuery('Кружка');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $capElement = $catalogElementsCollection->first();
            $capElement->setQuantity(10.22);
            $catalogElementsFilter->setQuery('Чайник');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $teapotElement = $catalogElementsCollection->first();
            $teapotElement->setQuantity(10.22);

            $links = new LinksCollection();
            $links->add($capElement);
            $links->add($teapotElement);
            $apiClient->leads()->link($lead, $links);
        }

        return response()->json(['success' => true]); //redirect()->route('amo.create')->with('success', 'Data successfully processed');
    }


    function printError(AmoCRMApiException $e): void
    {
        $errorTitle = $e->getTitle();
        $code = $e->getCode();
        $debugInfo = var_export($e->getLastRequestInfo(), true);

        $validationErrors = null;
        if ($e instanceof AmoCRMApiErrorResponseException) {
            $validationErrors = var_export($e->getValidationErrors(), true);
        }

        $error = <<<EOF
        Error: $errorTitle
        Code: $code
        Debug: $debugInfo
        EOF;

        if ($validationErrors !== null) {
            $error .= PHP_EOL . 'Validation-Errors: ' . $validationErrors . PHP_EOL;
        }

        echo '<pre>' . $error . '</pre>';
    }
}
