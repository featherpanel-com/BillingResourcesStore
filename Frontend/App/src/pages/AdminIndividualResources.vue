<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Loader2,
  Plus,
  Edit,
  Trash2,
  HardDrive,
  Cpu,
  Database,
  Server,
  Save,
  X,
} from "lucide-vue-next";
import { useToast } from "vue-toastification";
import axios from "axios";
import type { AxiosError } from "axios";

const toast = useToast();

interface IndividualResource {
  id?: number;
  name: string;
  description: string | null;
  resource_type: string;
  unit: string;
  price_per_unit: number;
  minimum_amount: number;
  maximum_amount: number | null;
  discount_percentage: number;
  discount_start_date: string | null;
  discount_end_date: string | null;
  discount_enabled: boolean;
  enabled: boolean;
  sort_order: number;
}

const resources = ref<IndividualResource[]>([]);
const loading = ref(false);
const showDialog = ref(false);
const editingResource = ref<IndividualResource | null>(null);
const saving = ref(false);

const resourceTypes = [
  { value: "memory_limit", label: "Memory Limit", icon: HardDrive },
  { value: "cpu_limit", label: "CPU Limit", icon: Cpu },
  { value: "disk_limit", label: "Disk Limit", icon: Database },
  { value: "server_limit", label: "Server Limit", icon: Server },
  { value: "database_limit", label: "Database Limit", icon: Database },
  { value: "backup_limit", label: "Backup Limit", icon: Database },
  { value: "allocation_limit", label: "Allocation Limit", icon: Server },
];

const getAvailableUnits = (
  resourceType: string
): Array<{ value: string; label: string }> => {
  if (resourceType === "memory_limit" || resourceType === "disk_limit") {
    return [
      { value: "MB", label: "MB" },
      { value: "GB", label: "GB" },
    ];
  }
  if (resourceType === "cpu_limit") {
    return [{ value: "%", label: "%" }];
  }
  // server_limit, database_limit, backup_limit, allocation_limit
  return [{ value: "count", label: "Count" }];
};

const formData = ref<Partial<IndividualResource>>({
  name: "",
  description: "",
  resource_type: "memory_limit",
  unit: "MB",
  price_per_unit: 0,
  minimum_amount: 1,
  maximum_amount: null,
  discount_percentage: 0,
  discount_start_date: undefined,
  discount_end_date: undefined,
  discount_enabled: false,
  enabled: true,
  sort_order: 0,
});

const loadResources = async () => {
  loading.value = true;
  try {
    const response = await axios.get(
      "/api/admin/billingresourcesstore/individual-resources"
    );
    if (response.data.success && response.data.data?.resources) {
      // Convert numeric boolean values (0/1) to actual booleans
      resources.value = response.data.data.resources.map(
        (
          resource: IndividualResource & {
            enabled?: number | boolean;
            discount_enabled?: number | boolean;
            discount_percentage?: string | number;
          }
        ) => ({
          ...resource,
          enabled: Boolean(resource.enabled),
          discount_enabled: Boolean(resource.discount_enabled),
          discount_percentage:
            typeof resource.discount_percentage === "string"
              ? parseFloat(resource.discount_percentage)
              : Number(resource.discount_percentage || 0),
        })
      );
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to load resources"
    );
  } finally {
    loading.value = false;
  }
};

const openCreateDialog = () => {
  editingResource.value = null;
  const defaultUnits = getAvailableUnits("memory_limit");
  formData.value = {
    name: "",
    description: "",
    resource_type: "memory_limit",
    unit: defaultUnits.length > 0 ? defaultUnits[0]?.value || "MB" : "MB",
    price_per_unit: 0,
    minimum_amount: 1,
    maximum_amount: null,
    discount_percentage: 0,
    discount_start_date: undefined,
    discount_end_date: undefined,
    discount_enabled: false,
    enabled: true,
    sort_order: 0,
  };
  showDialog.value = true;
};

const openEditDialog = (resource: IndividualResource) => {
  editingResource.value = resource;
  formData.value = {
    ...resource,
    enabled: Boolean(resource.enabled),
    discount_enabled: Boolean(resource.discount_enabled),
    discount_percentage:
      typeof resource.discount_percentage === "string"
        ? parseFloat(resource.discount_percentage)
        : Number(resource.discount_percentage || 0),
    discount_start_date: resource.discount_start_date || undefined,
    discount_end_date: resource.discount_end_date || undefined,
  };
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editingResource.value = null;
};

const saveResource = async () => {
  if (!formData.value.name?.trim()) {
    toast.error("Name is required");
    return;
  }

  if (!formData.value.price_per_unit || formData.value.price_per_unit <= 0) {
    toast.error("Price per unit must be greater than 0");
    return;
  }

  saving.value = true;
  try {
    // Convert boolean values back to numbers (0/1) for API
    const payload = {
      ...formData.value,
      enabled: formData.value.enabled ? 1 : 0,
      discount_enabled: formData.value.discount_enabled ? 1 : 0,
    };

    if (editingResource.value?.id) {
      // Update
      const response = await axios.put(
        `/api/admin/billingresourcesstore/individual-resources/${editingResource.value.id}`,
        payload
      );
      if (response.data.success) {
        toast.success("Resource updated successfully");
        await loadResources();
        closeDialog();
      }
    } else {
      // Create
      const response = await axios.post(
        "/api/admin/billingresourcesstore/individual-resources",
        payload
      );
      if (response.data.success) {
        toast.success("Resource created successfully");
        await loadResources();
        closeDialog();
      }
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to save resource"
    );
  } finally {
    saving.value = false;
  }
};

const deleteResource = async (id: number) => {
  if (!confirm("Are you sure you want to delete this resource?")) {
    return;
  }

  try {
    const response = await axios.delete(
      `/api/admin/billingresourcesstore/individual-resources/${id}`
    );
    if (response.data.success) {
      toast.success("Resource deleted successfully");
      await loadResources();
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to delete resource"
    );
  }
};

const getResourceTypeLabel = (type: string): string => {
  const found = resourceTypes.find((t) => t.value === type);
  return found?.label || type;
};

const getResourceTypeIcon = (type: string) => {
  const found = resourceTypes.find((t) => t.value === type);
  return found?.icon || HardDrive;
};

const formatCredits = (credits: number): string => {
  return credits.toLocaleString();
};

onMounted(() => {
  loadResources();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Individual Resources</h1>
          <p class="text-sm text-muted-foreground">
            Manage individual resource purchase options
          </p>
        </div>
        <Button @click="openCreateDialog">
          <Plus class="mr-2 h-4 w-4" />
          Add Resource
        </Button>
      </div>

      <div v-if="loading" class="flex items-center justify-center py-12">
        <Loader2 class="h-8 w-8 animate-spin" />
      </div>

      <div v-else-if="resources.length === 0" class="mb-6">
        <Card class="p-6">
          <div class="text-center py-8">
            <HardDrive class="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
            <h3 class="text-lg font-semibold mb-2">No Resources</h3>
            <p class="text-sm text-muted-foreground mb-4">
              Create your first individual resource purchase option.
            </p>
            <Button @click="openCreateDialog">
              <Plus class="mr-2 h-4 w-4" />
              Add Resource
            </Button>
          </div>
        </Card>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card
          v-for="resource in resources"
          :key="resource.id"
          class="p-6 flex flex-col"
        >
          <div class="flex-1">
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-2">
                <component
                  :is="getResourceTypeIcon(resource.resource_type)"
                  class="h-5 w-5 text-primary"
                />
                <div>
                  <h3 class="text-lg font-semibold">{{ resource.name }}</h3>
                  <p class="text-xs text-muted-foreground">
                    {{ getResourceTypeLabel(resource.resource_type) }}
                  </p>
                </div>
              </div>
              <div
                class="px-2 py-1 rounded text-xs font-semibold"
                :class="
                  resource.enabled
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                "
              >
                {{ resource.enabled ? "Enabled" : "Disabled" }}
              </div>
            </div>

            <p
              v-if="resource.description"
              class="text-sm text-muted-foreground mb-4"
            >
              {{ resource.description }}
            </p>

            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Price:</span>
                <span class="font-medium">
                  {{ formatCredits(resource.price_per_unit) }} credits per
                  {{ resource.unit }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Min Purchase:</span>
                <span class="font-medium">
                  {{ resource.minimum_amount }} {{ resource.unit }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Max Purchase:</span>
                <span class="font-medium">
                  {{
                    resource.maximum_amount
                      ? `${resource.maximum_amount} ${resource.unit}`
                      : "Unlimited"
                  }}
                </span>
              </div>
              <div
                v-if="
                  resource.discount_enabled && resource.discount_percentage > 0
                "
                class="flex justify-between"
              >
                <span class="text-muted-foreground">Discount:</span>
                <span class="font-medium text-green-600">
                  {{ resource.discount_percentage }}% OFF
                </span>
              </div>
            </div>
          </div>

          <div class="flex gap-2 mt-4">
            <Button
              @click="openEditDialog(resource)"
              variant="outline"
              class="flex-1"
            >
              <Edit class="mr-2 h-4 w-4" />
              Edit
            </Button>
            <Button
              @click="deleteResource(resource.id!)"
              variant="destructive"
              class="flex-1"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </Button>
          </div>
        </Card>
      </div>

      <!-- Create/Edit Dialog -->
      <Dialog :open="showDialog" @update:open="showDialog = $event">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {{ editingResource ? "Edit Resource" : "Create Resource" }}
            </DialogTitle>
            <DialogDescription>
              {{
                editingResource
                  ? "Update individual resource purchase options"
                  : "Create a new individual resource purchase option"
              }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4 py-4">
            <div>
              <Label for="name">Name *</Label>
              <Input
                id="name"
                v-model="formData.name"
                placeholder="e.g., RAM Upgrade, CPU Boost"
                class="mt-2"
              />
            </div>

            <div>
              <Label for="description">Description</Label>
              <Input
                id="description"
                :model-value="formData.description || ''"
                @update:model-value="
                  formData.description = ($event as string) || null
                "
                placeholder="Optional description"
                class="mt-2"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="resource_type">Resource Type *</Label>
                <select
                  id="resource_type"
                  v-model="formData.resource_type"
                  @change="
                    // Reset unit to first available unit when resource type changes
                    const availableUnits = getAvailableUnits(
                      formData.resource_type || 'memory_limit'
                    );
                    if (availableUnits.length > 0) {
                      formData.unit = availableUnits[0]?.value;
                    }
                  "
                  class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option
                    v-for="type in resourceTypes"
                    :key="type.value"
                    :value="type.value"
                  >
                    {{ type.label }}
                  </option>
                </select>
              </div>

              <div>
                <Label for="unit">Unit *</Label>
                <select
                  id="unit"
                  v-model="formData.unit"
                  class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option
                    v-for="unit in getAvailableUnits(
                      formData.resource_type || 'memory_limit'
                    )"
                    :key="unit.value"
                    :value="unit.value"
                  >
                    {{ unit.label }}
                  </option>
                </select>
                <p class="text-xs text-muted-foreground mt-1">
                  {{
                    formData.resource_type === "memory_limit" ||
                    formData.resource_type === "disk_limit"
                      ? "Only MB or GB allowed"
                      : formData.resource_type === "cpu_limit"
                      ? "Only % allowed"
                      : "Only Count allowed"
                  }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="price_per_unit">Price per Unit (Credits) *</Label>
                <Input
                  id="price_per_unit"
                  v-model.number="formData.price_per_unit"
                  type="number"
                  min="1"
                  placeholder="100"
                  class="mt-2"
                />
              </div>

              <div>
                <Label for="sort_order">Sort Order</Label>
                <Input
                  id="sort_order"
                  v-model.number="formData.sort_order"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="minimum_amount">Minimum Amount *</Label>
                <Input
                  id="minimum_amount"
                  v-model.number="formData.minimum_amount"
                  type="number"
                  min="1"
                  placeholder="1"
                  class="mt-2"
                />
              </div>

              <div>
                <Label for="maximum_amount">Maximum Amount</Label>
                <Input
                  id="maximum_amount"
                  :model-value="formData.maximum_amount ?? ''"
                  @update:model-value="
                    formData.maximum_amount =
                      $event === '' || $event === null || $event === 0
                        ? null
                        : Number($event)
                  "
                  type="number"
                  min="0"
                  placeholder="0 (unlimited)"
                  class="mt-2"
                />
                <p class="text-xs text-muted-foreground mt-1">
                  Leave empty or 0 for unlimited
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <input
                id="enabled"
                v-model="formData.enabled"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300"
              />
              <Label for="enabled" class="cursor-pointer">Enabled</Label>
            </div>

            <div class="border-t pt-4 mt-4">
              <h4 class="font-semibold mb-3">Discount Settings</h4>

              <div class="flex items-center gap-2 mb-4">
                <input
                  id="discount_enabled"
                  v-model="formData.discount_enabled"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300"
                />
                <Label for="discount_enabled" class="cursor-pointer">
                  Enable Discount
                </Label>
              </div>

              <div v-if="formData.discount_enabled" class="space-y-4">
                <div>
                  <Label for="discount_percentage">Discount Percentage</Label>
                  <Input
                    id="discount_percentage"
                    v-model.number="formData.discount_percentage"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Percentage discount (0-100)
                  </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <Label for="discount_start_date">Start Date</Label>
                    <Input
                      id="discount_start_date"
                      :model-value="formData.discount_start_date || ''"
                      @update:model-value="
                        formData.discount_start_date =
                          ($event as string) || undefined
                      "
                      type="datetime-local"
                      class="mt-2"
                    />
                  </div>
                  <div>
                    <Label for="discount_end_date">End Date</Label>
                    <Input
                      id="discount_end_date"
                      :model-value="formData.discount_end_date || ''"
                      @update:model-value="
                        formData.discount_end_date =
                          ($event as string) || undefined
                      "
                      type="datetime-local"
                      class="mt-2"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" @click="closeDialog">
              <X class="mr-2 h-4 w-4" />
              Cancel
            </Button>
            <Button @click="saveResource" :disabled="saving">
              <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
              <Save v-else class="mr-2 h-4 w-4" />
              {{ saving ? "Saving..." : "Save" }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
