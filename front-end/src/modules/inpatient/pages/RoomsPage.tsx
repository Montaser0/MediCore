"use client";

import { FormEvent, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Badge } from "@/components/ui/Badge";
import { Panel } from "@/components/ui/Panel";
import { arabicLabel } from "@/i18n/arabicLabels";
import { useAuthStore } from "@/stores/auth-store";

const BED_STATUSES = ["available", "reserved", "occupied", "cleaning", "maintenance"] as const;

type BedStatus = (typeof BED_STATUSES)[number];

type BedRow = {
  id: number;
  bed_number?: string;
  status?: string;
  status_label?: string;
  occupant?: { patient_name?: string } | null;
};

type RoomRow = {
  id: number;
  room_number?: string;
  room_type?: string | null;
  capacity?: number;
  daily_rate?: number;
  beds?: BedRow[];
};

type WardBoard = {
  ward?: { id?: number; name?: string; daily_rate?: number };
  rooms?: RoomRow[];
};

type OccupancyBoard = {
  wards?: WardBoard[];
};

type RoomDraft = {
  id: number;
  ward_id: string;
  room_number: string;
  room_type: string;
  bed_count: string;
  daily_rate: string;
};

type WardOption = {
  id: number;
  name?: string;
};

function statusTone(status?: string): "success" | "warning" | "danger" | "info" | "default" {
  if (status === "available") return "success";
  if (status === "occupied") return "warning";
  if (status === "maintenance") return "danger";
  if (status === "reserved" || status === "cleaning") return "info";
  return "default";
}

function statusSelectClass(status?: string) {
  const tone = statusTone(status);
  return tone === "default" ? undefined : `his-select-${tone}`;
}

export function RoomsPage() {
  const t = useTranslations();
  const canManage = useAuthStore((state) => state.hasPermission)("inpatient.beds.manage");
  const qc = useQueryClient();
  const [wardId, setWardId] = useState("");
  const [roomNumber, setRoomNumber] = useState("");
  const [bedCount, setBedCount] = useState("1");
  const [roomType, setRoomType] = useState("");
  const [dailyRate, setDailyRate] = useState("");
  const [filterWard, setFilterWard] = useState("");
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState<RoomDraft | null>(null);
  const [saving, setSaving] = useState(false);
  const [savingBed, setSavingBed] = useState<number | null>(null);

  const wards = useQuery({
    queryKey: ["inpatient", "wards"],
    queryFn: async () => {
      const res = await apiGet<WardOption[]>("/inpatient/wards");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const board = useQuery({
    queryKey: ["inpatient", "occupancy"],
    queryFn: async () => {
      const res = await apiGet<OccupancyBoard>("/inpatient/beds/occupancy");
      return res.data ?? { wards: [] };
    },
  });

  async function refresh() {
    await Promise.all([wards.refetch(), board.refetch()]);
  }

  async function addRoom(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    try {
      await apiPost(`/inpatient/wards/${wardId}/rooms`, {
        room_number: roomNumber.trim(),
        bed_count: Number(bedCount),
        room_type: roomType.trim() || undefined,
        daily_rate: dailyRate === "" ? undefined : Number(dailyRate),
      });
      toast.success(t("inpatient.roomAdded"));
      setAdding(false);
      setRoomNumber("");
      setBedCount("1");
      setRoomType("");
      setDailyRate("");
      await qc.invalidateQueries({ queryKey: ["inpatient"] });
      await qc.invalidateQueries({ queryKey: ["journey-catalogs"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  function openEdit(group: WardBoard, room: RoomRow) {
    setEditing({
      id: room.id,
      ward_id: String(group.ward?.id ?? ""),
      room_number: room.room_number ?? "",
      room_type: room.room_type ?? "",
      bed_count: String(room.beds?.length ?? room.capacity ?? 1),
      daily_rate: room.daily_rate != null ? String(room.daily_rate) : "",
    });
  }

  async function saveEdit(event: FormEvent) {
    event.preventDefault();
    if (!editing) return;
    setSaving(true);
    try {
      await apiPatch(`/inpatient/rooms/${editing.id}`, {
        ward_id: Number(editing.ward_id),
        room_number: editing.room_number.trim(),
        room_type: editing.room_type.trim() || null,
        bed_count: Number(editing.bed_count),
        daily_rate: editing.daily_rate === "" ? null : Number(editing.daily_rate),
      });
      toast.success(t("workspace.saved"));
      setEditing(null);
      await qc.invalidateQueries({ queryKey: ["inpatient"] });
      await qc.invalidateQueries({ queryKey: ["journey-catalogs"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function removeRoom(room: RoomRow) {
    if (!window.confirm(t("crud.confirmDelete"))) return;
    try {
      await apiDelete(`/inpatient/rooms/${room.id}`);
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: ["inpatient"] });
      await qc.invalidateQueries({ queryKey: ["journey-catalogs"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  async function changeStatus(bed: BedRow, status: BedStatus) {
    if (!bed.id || bed.status === status) return;
    setSavingBed(bed.id);
    try {
      await apiPost(`/inpatient/beds/${bed.id}/status`, { status });
      await qc.invalidateQueries({ queryKey: ["inpatient"] });
      await qc.invalidateQueries({ queryKey: ["journey-catalogs"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSavingBed(null);
    }
  }

  const groups = (board.data?.wards ?? []).filter(
    (group) => !filterWard || String(group.ward?.id) === filterWard,
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("inpatient.rooms")}>
        <Button onClick={() => refresh()} disabled={wards.isFetching || board.isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
        {canManage ? (
          <Button variant="primary" onClick={() => setAdding(true)}>
            {t("inpatient.addRoom")}
          </Button>
        ) : null}
      </PageToolbar>

      <div className="min-h-0 flex-1 space-y-2 overflow-auto p-2">
        <div className="flex flex-wrap gap-1">
          {BED_STATUSES.map((status) => (
            <Badge key={status} tone={statusTone(status)}>
              {arabicLabel(status)}
            </Badge>
          ))}
        </div>

        <Panel
          title={t("inpatient.rooms")}
          actions={
            <Select value={filterWard} onChange={(event) => setFilterWard(event.target.value)}>
              <option value="">{t("inpatient.wards")}</option>
              {(wards.data ?? []).map((ward) => (
                <option key={ward.id} value={ward.id}>
                  {ward.name ?? ward.id}
                </option>
              ))}
            </Select>
          }
        >
          <div className="his-table-wrap">
            <table className="his-table">
              <thead>
                <tr>
                  <th>{t("inpatient.wards")}</th>
                  <th>{t("inpatient.roomNumber")}</th>
                  <th>{t("inpatient.roomType")}</th>
                  <th>{t("inpatient.bedCount")}</th>
                  <th>{t("crud.price")}</th>
                  <th>{t("inpatient.beds")}</th>
                  <th>{t("app.status")}</th>
                  <th>{t("patients.fullName")}</th>
                  {canManage ? <th>{t("app.actions")}</th> : null}
                </tr>
              </thead>
              <tbody>
                {board.isLoading ? (
                  <tr>
                    <td colSpan={canManage ? 9 : 8} className="py-8 text-center text-[var(--his-muted)]">
                      {t("app.loading")}
                    </td>
                  </tr>
                ) : groups.length === 0 ? (
                  <tr>
                    <td colSpan={canManage ? 9 : 8} className="py-8 text-center text-[var(--his-muted)]">
                      {t("app.noData")}
                    </td>
                  </tr>
                ) : (
                  groups.flatMap((group) =>
                    (group.rooms ?? []).flatMap((room) => {
                      const beds = room.beds?.length ? room.beds : [null];
                      return beds.map((bed, index) => (
                        <tr key={bed?.id ?? `room-${room.id}`}>
                          {index === 0 ? (
                            <>
                              <td rowSpan={beds.length}>{group.ward?.name ?? "—"}</td>
                              <td rowSpan={beds.length} className="font-semibold tabular-nums">
                                {room.room_number ?? "—"}
                              </td>
                              <td rowSpan={beds.length}>{room.room_type || "—"}</td>
                              <td rowSpan={beds.length} className="tabular-nums">
                                {room.beds?.length ?? room.capacity ?? 0}
                              </td>
                              <td rowSpan={beds.length} className="tabular-nums">
                                {room.daily_rate ?? group.ward?.daily_rate ?? "—"}
                              </td>
                            </>
                          ) : null}
                          <td className="tabular-nums">{bed?.bed_number ?? "—"}</td>
                          <td>
                            {bed == null ? (
                              "—"
                            ) : bed.occupant?.patient_name || !canManage ? (
                              <Badge tone={statusTone(bed.status)}>{arabicLabel(bed.status)}</Badge>
                            ) : (
                              <Select
                                value={bed.status ?? "available"}
                                disabled={savingBed === bed.id}
                                className={statusSelectClass(bed.status)}
                                onChange={(event) => changeStatus(bed, event.target.value as BedStatus)}
                              >
                                {BED_STATUSES.map((status) => (
                                  <option key={status} value={status} className={statusSelectClass(status)}>
                                    {arabicLabel(status)}
                                  </option>
                                ))}
                              </Select>
                            )}
                          </td>
                          <td>{bed?.occupant?.patient_name ?? "—"}</td>
                          {canManage && index === 0 ? (
                            <td rowSpan={beds.length}>
                              <div className="flex gap-1">
                                <Button onClick={() => openEdit(group, room)}>{t("app.edit")}</Button>
                                <Button variant="danger" onClick={() => removeRoom(room)}>
                                  {t("app.delete")}
                                </Button>
                              </div>
                            </td>
                          ) : null}
                        </tr>
                      ));
                    }),
                  )
                )}
              </tbody>
            </table>
          </div>
        </Panel>
      </div>

      {adding ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={addRoom} className="his-panel w-full max-w-lg space-y-2 p-3">
            <div className="text-sm font-semibold">{t("inpatient.addRoom")}</div>
            {wards.data?.length ? (
              <>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("inpatient.wards")}</span>
                  <Select required value={wardId} onChange={(event) => setWardId(event.target.value)}>
                    <option value="">{t("inpatient.wards")}</option>
                    {wards.data.map((ward) => (
                      <option key={ward.id} value={ward.id}>
                        {ward.name ?? ward.id}
                      </option>
                    ))}
                  </Select>
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("inpatient.roomNumber")}</span>
                  <Input required value={roomNumber} onChange={(event) => setRoomNumber(event.target.value)} />
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("inpatient.bedCount")}</span>
                  <Input
                    required
                    type="number"
                    min={1}
                    max={20}
                    value={bedCount}
                    onChange={(event) => setBedCount(event.target.value)}
                  />
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("inpatient.roomType")}</span>
                  <Input value={roomType} onChange={(event) => setRoomType(event.target.value)} />
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("crud.price")}</span>
                  <Input
                    type="number"
                    min={0}
                    value={dailyRate}
                    onChange={(event) => setDailyRate(event.target.value)}
                  />
                </label>
              </>
            ) : (
              <p className="m-0 text-[12px] text-[var(--his-muted)]">{t("inpatient.noWards")}</p>
            )}
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setAdding(false)}>
                {t("app.cancel")}
              </Button>
              <Button type="submit" variant="primary" disabled={saving || !wards.data?.length}>
                {t("app.save")}
              </Button>
            </div>
          </form>
        </div>
      ) : null}

      {editing ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={saveEdit} className="his-panel w-full max-w-lg space-y-2 p-3">
            <div className="text-sm font-semibold">{t("app.edit")}</div>
            <label className="grid gap-1 text-[12px]">
              <span>{t("inpatient.wards")}</span>
              <Select
                required
                value={editing.ward_id}
                onChange={(event) => setEditing({ ...editing, ward_id: event.target.value })}
              >
                {(wards.data ?? []).map((ward) => (
                  <option key={ward.id} value={ward.id}>
                    {ward.name ?? ward.id}
                  </option>
                ))}
              </Select>
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("inpatient.roomNumber")}</span>
              <Input
                required
                value={editing.room_number}
                onChange={(event) => setEditing({ ...editing, room_number: event.target.value })}
              />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("inpatient.bedCount")}</span>
              <Input
                required
                type="number"
                min={1}
                max={20}
                value={editing.bed_count}
                onChange={(event) => setEditing({ ...editing, bed_count: event.target.value })}
              />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("inpatient.roomType")}</span>
              <Input
                value={editing.room_type}
                onChange={(event) => setEditing({ ...editing, room_type: event.target.value })}
              />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("crud.price")}</span>
              <Input
                type="number"
                min={0}
                value={editing.daily_rate}
                onChange={(event) => setEditing({ ...editing, daily_rate: event.target.value })}
              />
            </label>
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setEditing(null)}>
                {t("app.cancel")}
              </Button>
              <Button type="submit" variant="primary" disabled={saving}>
                {t("app.save")}
              </Button>
            </div>
          </form>
        </div>
      ) : null}
    </div>
  );
}
