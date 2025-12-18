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
import type { ResourcePackage } from "@/composables/useStoreAPI";

const toast = useToast();

const loading = ref(false);
const packages = ref<ResourcePackage[]>([]);
const showDialog = ref(false);
const editingPackage = ref<ResourcePackage | null>(null);
const saving = ref(false);

const formData = ref<Partial<ResourcePackage>>({
  name: "",
  description: "",
  memory_limit: 0,
  cpu_limit: 0,
  disk_limit: 0,
  server_limit: 0,
  database_limit: 0,
  backup_limit: 0,
  allocation_limit: 0,
  price: 0,
  enabled: true,
  sort_order: 0,
  discount_percentage: 0,
  discount_start_date: undefined,
  discount_end_date: undefined,
  discount_enabled: false,
});

const formatBytes = (mb: number): string => {
  if (mb === 0) return "0 MB";
  if (mb >= 1024) {
    return `${(mb / 1024).toFixed(2)} GB`;
  }
  return `${mb} MB`;
};

const formatPercentage = (value: number): string => {
  return `${value}%`;
};

const formatCredits = (credits: number): string => {
  return credits.toLocaleString();
};

const loadPackages = async () => {
  loading.value = true;
  try {
    const response = await axios.get(
      "/api/admin/billingresourcesstore/packages"
    );
    if (response.data.success && response.data.data?.packages) {
      packages.value = response.data.data.packages;
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to load packages"
    );
  } finally {
    loading.value = false;
  }
};

const openCreateDialog = () => {
  editingPackage.value = null;
  formData.value = {
    name: "",
    description: "",
    memory_limit: 0,
    cpu_limit: 0,
    disk_limit: 0,
    server_limit: 0,
    database_limit: 0,
    backup_limit: 0,
    allocation_limit: 0,
    price: 0,
    enabled: true,
    sort_order: 0,
  };
  showDialog.value = true;
};

const openEditDialog = (pkg: ResourcePackage) => {
  editingPackage.value = pkg;
  formData.value = {
    ...pkg,
    discount_start_date: pkg.discount_start_date || undefined,
    discount_end_date: pkg.discount_end_date || undefined,
  };
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editingPackage.value = null;
};

const savePackage = async () => {
  if (!formData.value.name || formData.value.name.trim() === "") {
    toast.error("Package name is required");
    return;
  }

  if (!formData.value.price || formData.value.price <= 0) {
    toast.error("Price must be greater than 0");
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: formData.value.name.trim(),
      description: formData.value.description?.trim() || "",
      memory_limit: Math.max(0, formData.value.memory_limit || 0),
      cpu_limit: Math.max(0, formData.value.cpu_limit || 0),
      disk_limit: Math.max(0, formData.value.disk_limit || 0),
      server_limit: Math.max(0, formData.value.server_limit || 0),
      database_limit: Math.max(0, formData.value.database_limit || 0),
      backup_limit: Math.max(0, formData.value.backup_limit || 0),
      allocation_limit: Math.max(0, formData.value.allocation_limit || 0),
      price: Math.max(0, formData.value.price || 0),
      enabled: formData.value.enabled ?? true,
      sort_order: formData.value.sort_order || 0,
    };

    if (editingPackage.value) {
      const response = await axios.put(
        `/api/admin/billingresourcesstore/packages/${editingPackage.value.id}`,
        payload
      );
      if (response.data.success) {
        toast.success("Package updated successfully");
      }
    } else {
      const response = await axios.post(
        "/api/admin/billingresourcesstore/packages",
        payload
      );
      if (response.data.success) {
        toast.success("Package created successfully");
      }
    }

    closeDialog();
    await loadPackages();
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to save package"
    );
  } finally {
    saving.value = false;
  }
};

const deletePackage = async (pkg: ResourcePackage) => {
  if (!confirm(`Are you sure you want to delete "${pkg.name}"?`)) {
    return;
  }

  try {
    const response = await axios.delete(
      `/api/admin/billingresourcesstore/packages/${pkg.id}`
    );
    if (response.data.success) {
      toast.success("Package deleted successfully");
      await loadPackages();
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to delete package"
    );
  }
};

onMounted(() => {
  loadPackages();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Resource Store Management</h1>
          <p class="text-sm text-muted-foreground">
            Manage resource packages available for purchase
          </p>
        </div>
        <Button @click="openCreateDialog">
          <Plus class="mr-2 h-4 w-4" />
          Create Package
        </Button>
      </div>

      <div v-if="loading" class="flex items-center justify-center py-12">
        <Loader2 class="h-8 w-8 animate-spin" />
      </div>

      <div v-else-if="packages.length === 0" class="mb-6">
        <Card class="p-6">
          <div class="text-center py-8">
            <h3 class="text-lg font-semibold mb-2">No Packages</h3>
            <p class="text-sm text-muted-foreground mb-4">
              Create your first resource package to get started.
            </p>
            <Button @click="openCreateDialog">
              <Plus class="mr-2 h-4 w-4" />
              Create Package
            </Button>
          </div>
        </Card>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card v-for="pkg in packages" :key="pkg.id" class="p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <h3 class="text-xl font-semibold">{{ pkg.name }}</h3>
                <span
                  v-if="!pkg.enabled"
                  class="text-xs px-2 py-1 bg-muted rounded"
                >
                  Disabled
                </span>
              </div>
              <p
                v-if="pkg.description"
                class="text-sm text-muted-foreground mb-2"
              >
                {{ pkg.description }}
              </p>
              <div class="text-2xl font-bold text-primary">
                {{ formatCredits(pkg.price) }}
                <span class="text-sm font-normal text-muted-foreground"
                  >Credits</span
                >
              </div>
            </div>
          </div>

          <div class="space-y-1 mb-4 text-sm">
            <div v-if="pkg.memory_limit > 0" class="flex items-center gap-2">
              <HardDrive class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Memory:</span>
              <span class="font-medium">{{
                formatBytes(pkg.memory_limit)
              }}</span>
            </div>
            <div v-if="pkg.cpu_limit > 0" class="flex items-center gap-2">
              <Cpu class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">CPU:</span>
              <span class="font-medium">{{
                formatPercentage(pkg.cpu_limit)
              }}</span>
            </div>
            <div v-if="pkg.disk_limit > 0" class="flex items-center gap-2">
              <Database class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Disk:</span>
              <span class="font-medium">{{ formatBytes(pkg.disk_limit) }}</span>
            </div>
            <div v-if="pkg.server_limit > 0" class="flex items-center gap-2">
              <Server class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Servers:</span>
              <span class="font-medium">{{ pkg.server_limit }}</span>
            </div>
            <div v-if="pkg.database_limit > 0" class="flex items-center gap-2">
              <Database class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Databases:</span>
              <span class="font-medium">{{
                pkg.database_limit === 0 ? "∞" : pkg.database_limit
              }}</span>
            </div>
            <div v-if="pkg.backup_limit > 0" class="flex items-center gap-2">
              <Database class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Backups:</span>
              <span class="font-medium">{{
                pkg.backup_limit === 0 ? "∞" : pkg.backup_limit
              }}</span>
            </div>
            <div
              v-if="pkg.allocation_limit > 0"
              class="flex items-center gap-2"
            >
              <Server class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Allocations:</span>
              <span class="font-medium">{{
                pkg.allocation_limit === 0 ? "∞" : pkg.allocation_limit
              }}</span>
            </div>
          </div>

          <div class="flex gap-2">
            <Button
              @click="openEditDialog(pkg)"
              variant="outline"
              class="flex-1"
            >
              <Edit class="mr-2 h-4 w-4" />
              Edit
            </Button>
            <Button
              @click="deletePackage(pkg)"
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
      <Dialog v-model:open="showDialog">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {{ editingPackage ? "Edit Package" : "Create Package" }}
            </DialogTitle>
            <DialogDescription>
              {{
                editingPackage
                  ? "Update package details"
                  : "Create a new resource package"
              }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4 py-4">
            <div>
              <Label for="name">Package Name *</Label>
              <Input
                id="name"
                v-model="formData.name"
                placeholder="Starter Pack"
                class="mt-2"
              />
            </div>

            <div>
              <Label for="description">Description</Label>
              <Input
                id="description"
                v-model="formData.description"
                placeholder="Package description"
                class="mt-2"
              />
            </div>

            <div>
              <Label for="price">Price (Credits) *</Label>
              <Input
                id="price"
                v-model.number="formData.price"
                type="number"
                min="0"
                placeholder="1000"
                class="mt-2"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="memory_limit">Memory (MB)</Label>
                <Input
                  id="memory_limit"
                  v-model.number="formData.memory_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="cpu_limit">CPU (%)</Label>
                <Input
                  id="cpu_limit"
                  v-model.number="formData.cpu_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="disk_limit">Disk (MB)</Label>
                <Input
                  id="disk_limit"
                  v-model.number="formData.disk_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="server_limit">Server Limit</Label>
                <Input
                  id="server_limit"
                  v-model.number="formData.server_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="database_limit">Database Limit</Label>
                <Input
                  id="database_limit"
                  v-model.number="formData.database_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="backup_limit">Backup Limit</Label>
                <Input
                  id="backup_limit"
                  v-model.number="formData.backup_limit"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="mt-2"
                />
              </div>
              <div>
                <Label for="allocation_limit">Allocation Limit</Label>
                <Input
                  id="allocation_limit"
                  v-model.number="formData.allocation_limit"
                  type="number"
                  min="0"
                  placeholder="0"
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
                <Label for="discount_enabled" class="cursor-pointer"
                  >Enable Discount</Label
                >
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
            <Button variant="outline" @click="closeDialog" :disabled="saving">
              <X class="mr-2 h-4 w-4" />
              Cancel
            </Button>
            <Button @click="savePackage" :disabled="saving">
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
