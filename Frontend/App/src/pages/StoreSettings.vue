<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2, Save, Settings, Coins } from "lucide-vue-next";
import { useToast } from "vue-toastification";
import axios from "axios";
import type { AxiosError } from "axios";

const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const settings = ref<{
  store_enabled?: boolean;
  maintenance_message?: string;
  global_discount?: number;
  minimum_purchase_for_discount?: number;
  bulk_discounts?: Record<string, number>;
  max_discount?: number;
  front_page_display?: string;
  invoice_generation_enabled?: boolean;
  invoice_generation_packages?: boolean;
  invoice_generation_individual?: boolean;
}>({});

const loadSettings = async () => {
  loading.value = true;
  try {
    const response = await axios.get(
      "/api/admin/billingresourcesstore/settings"
    );
    if (response.data.success && response.data.data?.settings) {
      settings.value = response.data.data.settings;
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to load settings"
    );
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  try {
    const response = await axios.put(
      "/api/admin/billingresourcesstore/settings",
      settings.value
    );
    if (response.data.success) {
      toast.success("Settings saved successfully");
      await loadSettings();
    }
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError.response?.data?.error_message || "Failed to save settings"
    );
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadSettings();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-4xl">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Store Settings</h1>
          <p class="text-sm text-muted-foreground">
            Configure store behavior, discounts, and individual resource pricing
          </p>
        </div>
        <Button @click="saveSettings" :disabled="saving">
          <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
          <Save v-else class="mr-2 h-4 w-4" />
          {{ saving ? "Saving..." : "Save Settings" }}
        </Button>
      </div>

      <div v-if="loading" class="flex items-center justify-center py-12">
        <Loader2 class="h-8 w-8 animate-spin" />
      </div>

      <div v-else class="space-y-6">
        <!-- Store Status -->
        <Card class="p-6">
          <div class="flex items-center gap-2 mb-4">
            <Settings class="h-5 w-5 text-primary" />
            <h2 class="text-lg font-semibold">Store Status</h2>
          </div>
          <div class="space-y-4">
            <div class="flex items-center gap-2">
              <input
                id="store_enabled"
                v-model="settings.store_enabled"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300"
              />
              <Label for="store_enabled" class="cursor-pointer"
                >Store Enabled</Label
              >
            </div>
            <div>
              <Label for="maintenance_message">Maintenance Message</Label>
              <Input
                id="maintenance_message"
                v-model="settings.maintenance_message"
                placeholder="The store is currently under maintenance..."
                class="mt-2"
              />
            </div>
          </div>
        </Card>

        <!-- Individual Resource Purchases -->
        <Card class="p-6">
          <div class="flex items-center gap-2 mb-4">
            <Coins class="h-5 w-5 text-primary" />
            <h2 class="text-lg font-semibold">Individual Resource Purchases</h2>
          </div>
          <div class="space-y-4">
            <div
              class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800"
            >
              <p class="text-sm text-blue-900 dark:text-blue-200">
                <strong>Note:</strong> Individual resources are now managed
                through the
                <a class="underline font-semibold"> Individual Resources </a>
                page. You can create, edit, and delete individual resource
                purchase options there.
              </p>
            </div>
          </div>
        </Card>

        <!-- Discount Settings -->
        <Card class="p-6">
          <div class="flex items-center gap-2 mb-4">
            <Coins class="h-5 w-5 text-primary" />
            <h2 class="text-lg font-semibold">Discount Settings</h2>
          </div>
          <div class="space-y-4">
            <div>
              <Label for="global_discount">Global Discount (%)</Label>
              <Input
                id="global_discount"
                v-model.number="settings.global_discount"
                type="number"
                min="0"
                max="100"
                step="0.01"
                placeholder="0"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Applied to all packages
              </p>
            </div>

            <div>
              <Label for="max_discount">Maximum Discount (%)</Label>
              <Input
                id="max_discount"
                v-model.number="settings.max_discount"
                type="number"
                min="0"
                max="100"
                step="0.01"
                placeholder="50"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Maximum discount that can be applied (caps all discounts)
              </p>
            </div>

            <div>
              <Label for="minimum_purchase_for_discount"
                >Minimum Purchase for Discount (Credits)</Label
              >
              <Input
                id="minimum_purchase_for_discount"
                v-model.number="settings.minimum_purchase_for_discount"
                type="number"
                min="0"
                placeholder="0"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Minimum purchase amount required for discounts to apply
              </p>
            </div>
          </div>
        </Card>

        <!-- Invoice Generation Settings -->
        <Card class="p-6">
          <div class="flex items-center gap-2 mb-4">
            <Coins class="h-5 w-5 text-primary" />
            <h2 class="text-lg font-semibold">Invoice Generation</h2>
          </div>
          <div class="space-y-6">
            <div>
              <div class="flex items-center gap-2 mb-2">
                <input
                  id="invoice_generation_enabled"
                  v-model="settings.invoice_generation_enabled"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300"
                />
                <Label
                  for="invoice_generation_enabled"
                  class="text-sm font-medium"
                >
                  Enable Invoice Generation
                </Label>
              </div>
              <p class="text-xs text-muted-foreground ml-6">
                When enabled, invoices will be automatically generated for
                purchases using billingcore. Users must have billing information
                set up.
              </p>
            </div>

            <div
              v-if="settings.invoice_generation_enabled"
              class="space-y-4 pl-6 border-l-2 border-border"
            >
              <div>
                <div class="flex items-center gap-2 mb-2">
                  <input
                    id="invoice_generation_packages"
                    v-model="settings.invoice_generation_packages"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300"
                  />
                  <Label
                    for="invoice_generation_packages"
                    class="text-sm font-medium"
                  >
                    Generate Invoices for Resource Packages
                  </Label>
                </div>
                <p class="text-xs text-muted-foreground ml-6">
                  Create invoices when users purchase resource packages
                </p>
              </div>

              <div>
                <div class="flex items-center gap-2 mb-2">
                  <input
                    id="invoice_generation_individual"
                    v-model="settings.invoice_generation_individual"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300"
                  />
                  <Label
                    for="invoice_generation_individual"
                    class="text-sm font-medium"
                  >
                    Generate Invoices for Individual Resources
                  </Label>
                </div>
                <p class="text-xs text-muted-foreground ml-6">
                  Create invoices when users purchase any individual resources
                </p>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
