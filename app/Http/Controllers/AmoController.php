<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Filters\CatalogElementsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\AttachmentNote;
use AmoCRM\Models\TaskModel;
use App\Enums\AmoCustomFieldsEnums;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use AmoCRM\Client\AmoCRMApiClientException;
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method printError(AmoCRMApiException|\Exception $e)
 */
class AmoController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $clientId = env('AMOCRM_CLIENT_ID');
        $clientSecret = env('AMOCRM_CLIENT_SECRET');
        $redirectUri = env('AMOCRM_REDIRECT_URI');
        $apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        return view('amo.index', [
            'apiClient' => $apiClient,
        ]);
    }

    public function create(Request $request):\Illuminate\View\View
    {
        $apiClient = new \AmoCRM\Client\AmoCRMApiClient(env('AMOCRM_CLIENT_ID'), env('AMOCRM_CLIENT_SECRET'), env('AMOCRM_REDIRECT_URI'));
        if (isset($_GET['code'])) {
            $auth = $_GET['code'];
            try {
                $oauth = $apiClient->getOAuthClient();
                $oauth->setBaseDomain('ankulagin.amocrm.ru');
                $accessToken = $oauth->getAccessTokenByCode($auth);  //$this->accessToken
                $request->session()->put('amo_access_token', $accessToken);
            } catch (\AmoCRM\Exceptions\AmoCRMoAuthApiException $e) {
                echo $e->getMessage();
            }
        }
        return view('amo.create');
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
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
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Если данные валидны, выполняем вашу логику обработки данных
        $apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain('ankulagin.amocrm.ru')
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
        //CUSTOMER AND TESTING
        try {
            $leadsService = $apiClient->leads();
            $phoneToCheck = $request->json('phone');
            $allContact = $apiClient->contacts()->get(with: ['leads'])->all();
            /*
             * @var ContactModel $contact
             */
            foreach ($allContact as $contact) {
                if ($contact !== null) {
                    $customFieldsValues = $contact->getCustomFieldsValues();
                    if ($customFieldsValues && method_exists($customFieldsValues,'getBy')){
                        $phoneField = $customFieldsValues->getBy('fieldCode', AmoCustomFieldsEnums::PHONE);
                        if ($phoneField && method_exists($phoneField, 'getValues')){
                            $phoneValues = $phoneField->getValues()->all();
                            foreach ($phoneValues as $phoneValue) {
                                if (method_exists($phoneValue, 'getValue')) {
                                    $currentPhoneValue = $phoneValue->getValue('value');
                                    if ($currentPhoneValue == $phoneToCheck) {
                                        if ($contact->getLeads() !== null) {
                                            /*
                                             * @var LeadModel $lead
                                             */
                                            foreach ($contact->getLeads() as $lead) {
                                                $leadId = $lead->getId();
                                                if ($leadsService->getOne($leadId)->getStatusId() == LeadModel::WON_STATUS_ID) {

                                                    $customer = new CustomerModel();
                                                    $customer->setName('Созданный покупатель ');
                                                    $customersService = $apiClient->customers();

                                                    try {
                                                        $customer = $customersService->addOne($customer);
                                                        $apiClient->customers()->link($customer, $contact);
                                                        //notes
                                                        $file = $apiClient->files()->get()->first();
                                                        //Создадим примечание с файлом
                                                        $noteModel = new AttachmentNote();
                                                        $noteModel->setEntityId($customer->getId())
                                                            ->setFileName($file->getFileName()) // название файла, которое будет отображаться в примечании
                                                            ->setVersionUuid($file->getVersionUuid())
                                                            ->setFileUuid($file->getUuid());
                                                        try {
                                                            $customerNotesService = $apiClient->notes(EntityTypesInterface::CUSTOMERS);
                                                            $noteModel = $customerNotesService->addOne($noteModel);
                                                        } catch (AmoCRMApiException $e) {
                                                            echo $e->getMessage();
                                                            $this->printError($e);
                                                        }
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
                        }
                    }
                }
            }
            //USER
            $usersCollection = $apiClient->users()->get();
            $randomKey = array_rand($usersCollection->toArray());
            // Получение случайного пользователя
            $randomUser = $usersCollection[$randomKey];
            // Получение ID случайного пользователя
            $randomUserId = $randomUser->getId();
            //CONTACT
            $contact = new ContactModel();
            $contact->setResponsibleUserId($randomUserId);
            $contact->setName($request->json('name'));
            $contact->setFirstName($request->json('name'));
            $contact->setLastName($request->json('surname'));
            // Создание коллекции полей контакта
            $customFields = new CustomFieldsValuesCollection();
            $contact->setCustomFieldsValues($customFields);
            //PHONE
            $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode(AmoCustomFieldsEnums::PHONE);
            $customFields->add($phoneField);
            // Установка значения поля
            $phoneField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($request->json('phone'))
                    )
            );
            //EMAIL
            $emailField = (new TextCustomFieldValuesModel())->setFieldCode(AmoCustomFieldsEnums::EMAIL);
            $customFields->add($emailField);
            // Установка значения поля
            $emailField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('email'))
                    )
            );
            //AGE
            // Проверка наличия поля 'Возвраст' по ID
            $ageField = (new TextCustomFieldValuesModel())->setFieldId(AmoCustomFieldsEnums::AGE_FIELDS_ID);
            $customFields->add($ageField);
            // Установка значения поля
            $ageField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('age'))
                    )
            );
            //GENDER
            $genderField = (new TextCustomFieldValuesModel())->setFieldId(AmoCustomFieldsEnums::GENDER_FIELDS_ID);
            $customFields->add($genderField);
            // Установка значения поля
            $genderField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($request->json('gender'))
                    )
            );
            //CONTACT
            $contactModel = $apiClient->contacts()->addOne($contact);
            //LEADS
            $lead = new LeadModel();
            $lead->setName('Новая очень новая сделка')
                ->setPrice(54321)
                ->setContacts((new ContactsCollection())
                ->add($contact));
            $leadsCollection = new LeadsCollection();
            $leadsCollection->add($lead);
            try {
                $leadsCollection = $leadsService->add($leadsCollection);

            } catch (AmoCRMApiException $e) {
                echo $e->getMessage();
                $this->printError($e);
            }
            //TASK
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
            //CATALOG ELEMENT LINK
            $catalogsCollection = $apiClient->catalogs()->get();
            $catalog = $catalogsCollection->getBy('name', 'Товары');
            $catalogElementsCollection = new CatalogElementsCollection();
            $catalogElementsService = $apiClient->catalogElements($catalog->getId());
            $catalogElementsFilter = new CatalogElementsFilter();
            $catalogElementsFilter->setQuery('Кружка');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $capElement = $catalogElementsCollection->first();
            $capElement->setQuantity(10);
            $catalogElementsFilter->setQuery('Чайник');
            $catalogElementsCollection = $catalogElementsService->get($catalogElementsFilter);
            $teapotElement = $catalogElementsCollection->first();
            $teapotElement->setQuantity(10);
            $links = new LinksCollection();
            $links->add($capElement);
            $links->add($teapotElement);
            $apiClient->leads()->link($lead, $links);
        } catch (AmoCRMApiException $e) {
            echo $e->getMessage();
            $this->printError($e);
        }
        return response()->json(['success' => true]);
    }
}
