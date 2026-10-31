<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Hospital\LabOrder;
use App\Models\Hospital\LabOrderItem;
use App\Models\Hospital\LabResult;
use App\Models\Hospital\LocalPatient;
use App\Services\Catalogs\LabTestDirectory;
use App\Services\Lab\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Módulos órdenes de laboratorio, recepción de muestras, ingreso de resultados y validación por bioquímico. */
class LabController extends Controller
{
    public function __construct(private readonly LabService $lab) {}

    public function tests(LabTestDirectory $tests): JsonResponse
    {
        return response()->json(['data' => $tests->all()]);
    }

    /** Órdenes de un paciente, con sus resultados (validados o pendientes), para el expediente. */
    public function patientOrders(string $patient): JsonResponse
    {
        $orders = LabOrder::query()->where('local_patient_id', $patient)->with(['items.currentResult', 'orderedBy'])->orderByDesc('created_at')->limit(30)->get();

        return response()->json(['data' => $orders->map(fn (LabOrder $o) => $this->order($o))]);
    }

    public function store(Request $request, string $patient): JsonResponse
    {
        $data = $request->validate([
            'tests' => ['required', 'array', 'min:1', 'max:20'], 'tests.*' => ['string', 'max:20'],
            'indication' => ['nullable', 'string', 'max:500'], 'priority' => ['nullable', 'in:routine,urgent'],
        ], ['tests.required' => 'Elija al menos una prueba.'], ['tests' => 'pruebas', 'indication' => 'indicación clínica']);
        $model = LocalPatient::query()->find($patient) ?? abort(404, 'Paciente no encontrado.');
        $order = $this->lab->order($request->user(), $request->attributes->get('hospital'), $model, $data['tests'], (string) ($data['indication'] ?? ''), $data['priority'] ?? 'routine', $request->ip());

        return response()->json(['data' => $this->order($order->load(['items.currentResult', 'orderedBy']))], 201);
    }

    /** Lista de trabajo del laboratorio: por recibir, por procesar (ingresar resultado) y por validar. */
    public function worklist(Request $request): JsonResponse
    {
        $request->validate(['etapa' => ['required', 'in:recepcion,resultados,validacion']]);
        $status = ['recepcion' => ['pending'], 'resultados' => ['received', 'resulted'], 'validacion' => ['resulted']][$request->query('etapa')];
        $items = LabOrderItem::query()->whereIn('lab_order_items.status', $status)->with(['order.patient', 'currentResult.enteredBy'])
            ->whereHas('order', fn ($q) => $q->where('status', '<>', 'cancelled'))
            ->when($request->query('etapa') === 'validacion', fn ($q) => $q->whereHas('currentResult', fn ($r) => $r->where('status', 'captured')))
            ->join('lab_orders', 'lab_orders.lab_order_id', '=', 'lab_order_items.lab_order_id')
            ->orderByRaw("lab_orders.priority = 'urgent' DESC")->orderBy('lab_orders.created_at')->select('lab_order_items.*')->limit(200)->get();

        return response()->json(['data' => $items->map(fn (LabOrderItem $i) => $this->item($i) + [
            'order_id' => $i->lab_order_id, 'priority' => $i->order->priority, 'indication' => $i->order->clinical_indication, 'ordered_at' => $i->order->created_at->toIso8601String(),
            'patient' => ['id' => $i->order->patient->local_patient_id, 'code' => $i->order->patient->code, 'name' => $i->order->patient->fullName()],
        ])]);
    }

    public function receive(Request $request, string $item): JsonResponse
    {
        return response()->json(['data' => $this->item($this->lab->receive($request->user(), $request->attributes->get('hospital'), $this->findItem($item), $request->ip()))]);
    }

    public function reject(Request $request, string $item): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return response()->json(['data' => $this->item($this->lab->reject($request->user(), $request->attributes->get('hospital'), $this->findItem($item), (string) ($data['reason'] ?? ''), $request->ip()))]);
    }

    public function result(Request $request, string $item): JsonResponse
    {
        $data = $request->validate(['value' => ['required', 'numeric', 'between:-100000,1000000'], 'unit' => ['required', 'string', 'max:20']], [], ['value' => 'valor', 'unit' => 'unidad']);
        $result = $this->lab->enterResult($request->user(), $request->attributes->get('hospital'), $this->findItem($item), (float) $data['value'], $data['unit'], $request->ip());

        return response()->json(['data' => ['id' => $result->lab_result_id, 'flag' => $result->flag, 'version' => $result->version_no]], 201);
    }

    public function review(Request $request, string $result): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:300']]);
        $model = LabResult::query()->find($result) ?? abort(404, 'Resultado no encontrado.');
        $reviewed = $this->lab->review($request->user(), $request->attributes->get('hospital'), $model, (bool) $data['approve'], (string) ($data['note'] ?? ''), $request->ip());

        return response()->json(['data' => ['id' => $reviewed->lab_result_id, 'status' => $reviewed->status]]);
    }

    private function findItem(string $id): LabOrderItem
    {
        return LabOrderItem::query()->with('order')->find($id) ?? abort(404, 'Prueba no encontrada.');
    }

    /** @return array<string, mixed> */
    private function order(LabOrder $o): array
    {
        return ['id' => $o->lab_order_id, 'status' => $o->status, 'priority' => $o->priority, 'indication' => $o->clinical_indication,
            'ordered_by' => $o->orderedBy?->display_name, 'created_at' => $o->created_at->toIso8601String(),
            'items' => $o->items->map(fn (LabOrderItem $i) => $this->item($i))->values()];
    }

    /** @return array<string, mixed> */
    private function item(LabOrderItem $i): array
    {
        $r = $i->relationLoaded('currentResult') ? $i->currentResult : $i->currentResult()->first();

        return [
            'id' => $i->lab_order_item_id, 'test_code' => $i->test_code, 'test_name' => $i->test_name, 'specimen_type' => $i->specimen_type, 'unit' => $i->unit,
            'ref_low' => $i->ref_low, 'ref_high' => $i->ref_high, 'status' => $i->status, 'specimen_code' => $i->specimen_code, 'rejection_reason' => $i->rejection_reason,
            'result' => $r ? ['id' => $r->lab_result_id, 'value' => $r->value_numeric, 'flag' => $r->flag, 'status' => $r->status, 'version' => $r->version_no,
                'entered_by' => $r->relationLoaded('enteredBy') ? $r->enteredBy?->display_name : null] : null,
        ];
    }
}
