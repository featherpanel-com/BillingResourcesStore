<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Loader2,
  ShoppingCart,
  HardDrive,
  Cpu,
  Database,
  Server,
  AlertCircle,
  Coins,
  Package,
  Zap,
} from "lucide-vue-next";
import { useStoreAPI, type ResourcePackage } from "@/composables/useStoreAPI";
import {
  useIndividualResourcesAPI,
  type ResourcePrices,
  type IndividualResource,
} from "@/composables/useIndividualResourcesAPI";
import { useToast } from "vue-toastification";
import axios from "axios";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

const toast = useToast();
const { loading, error, getPackages, purchasePackage } = useStoreAPI();
const { getResourcePrices, purchaseResources } = useIndividualResourcesAPI();

const packages = ref<ResourcePackage[]>([]);
const userCredits = ref<number>(0);
const purchasing = ref<number | null>(null);

const individualResources = ref<ResourcePrices | null>(null);
const purchasingIndividual = ref<number | null>(null);
const individualAmounts = ref<Record<number, number>>({});
const defaultTab = ref<"packages" | "individual">("packages");

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
  try {
    packages.value = await getPackages();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load packages");
  }
};

const loadCredits = async () => {
  try {
    const response = await axios.get("/api/user/billingcore/credits");
    if (response.data.success && response.data.data?.credits !== undefined) {
      userCredits.value = response.data.data.credits;
    }
  } catch (err) {
    console.error("Failed to load credits:", err);
  }
};

const handlePurchase = async (pkg: ResourcePackage) => {
  if (purchasing.value !== null) {
    return;
  }

  const finalPrice = pkg.final_price ?? pkg.price;
  if (userCredits.value < finalPrice) {
    toast.error(
      `Insufficient credits. Required: ${formatCredits(
        finalPrice
      )}, Available: ${formatCredits(userCredits.value)}`
    );
    return;
  }

  purchasing.value = pkg.id;

  try {
    await purchasePackage(pkg.id);
    toast.success(`Successfully purchased ${pkg.name}!`);

    // Reload credits and packages
    await Promise.all([loadCredits(), loadPackages()]);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to purchase package"
    );
  } finally {
    purchasing.value = null;
  }
};

const hasResource = (pkg: ResourcePackage): boolean => {
  return (
    pkg.memory_limit > 0 ||
    pkg.cpu_limit > 0 ||
    pkg.disk_limit > 0 ||
    pkg.server_limit > 0 ||
    pkg.database_limit > 0 ||
    pkg.backup_limit > 0 ||
    pkg.allocation_limit > 0
  );
};

const loadIndividualResources = async () => {
  try {
    individualResources.value = await getResourcePrices();
  } catch (err) {
    // Silently fail if individual purchases are disabled
    if (err instanceof Error && err.message.includes("disabled")) {
      individualResources.value = null;
    }
  }
};

const handleIndividualPurchase = async (resource: IndividualResource) => {
  if (purchasingIndividual.value !== null) {
    return;
  }

  if (!individualResources.value || !individualResources.value.enabled) {
    toast.error("Individual resource purchases are disabled");
    return;
  }

  const amount =
    individualAmounts.value[resource.id] || resource.minimum_amount;
  const minimum = resource.minimum_amount;
  const maximum = resource.maximum_amount;

  if (amount < minimum) {
    toast.error(`Minimum purchase is ${minimum} ${resource.unit}`);
    return;
  }

  if (maximum !== null && maximum !== undefined && amount > maximum) {
    toast.error(`Maximum purchase is ${maximum} ${resource.unit}`);
    return;
  }

  const totalPrice = resource.final_price_per_unit * amount;
  if (userCredits.value < totalPrice) {
    toast.error(
      `Insufficient credits. Required: ${formatCredits(
        totalPrice
      )}, Available: ${formatCredits(userCredits.value)}`
    );
    return;
  }

  purchasingIndividual.value = resource.id;

  try {
    await purchaseResources(resource.id, amount);
    toast.success(
      `Successfully purchased ${amount} ${resource.unit} of ${resource.name}!`
    );

    // Reload credits and individual resources
    await Promise.all([loadCredits(), loadIndividualResources()]);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to purchase resources"
    );
  } finally {
    purchasingIndividual.value = null;
  }
};

const getResourceIcon = (resourceType: string) => {
  if (resourceType === "memory_limit" || resourceType === "disk_limit") {
    return HardDrive;
  }
  if (resourceType === "cpu_limit") {
    return Cpu;
  }
  if (resourceType === "server_limit" || resourceType === "allocation_limit") {
    return Server;
  }
  return Database;
};

const getTotalCost = (resource: IndividualResource): number => {
  const amount =
    individualAmounts.value[resource.id] || resource.minimum_amount;
  return resource.final_price_per_unit * amount;
};

const canAffordPackage = (pkg: ResourcePackage): boolean => {
  const finalPrice = pkg.final_price ?? pkg.price;
  return userCredits.value >= finalPrice;
};

const canAffordResource = (resource: IndividualResource): boolean => {
  return userCredits.value >= getTotalCost(resource);
};

const loadStoreSettings = async () => {
  try {
    const response = await axios.get(
      "/api/admin/billingresourcesstore/settings"
    );
    if (
      response.data.success &&
      response.data.data?.settings?.front_page_display
    ) {
      defaultTab.value =
        response.data.data.settings.front_page_display === "individual"
          ? "individual"
          : "packages";
    }
  } catch (err) {
    // Silently fail, use default
    console.error("Failed to load store settings:", err);
  }
};

onMounted(() => {
  loadPackages();
  loadCredits();
  loadIndividualResources();
  loadStoreSettings();
});
</script>

<template>
  <div class="min-h-screen p-4 md:p-8">
    <div class="max-w-5xl mx-auto space-y-8">
      <!-- Header Section -->
      <div class="text-center space-y-4">
        <div class="flex items-center justify-center gap-3">
          <div class="relative">
            <div
              class="absolute inset-0 bg-primary/20 blur-2xl rounded-full"
            ></div>
            <ShoppingCart class="relative h-12 w-12 text-primary" />
          </div>
        </div>
        <div>
          <h1
            class="text-5xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
          >
            Resource Store
          </h1>
          <p class="text-lg text-muted-foreground mt-2">
            Purchase resources to expand your server capabilities
          </p>
        </div>
      </div>

      <!-- Credits Display -->
      <Card
        v-if="userCredits !== undefined"
        class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
      >
        <div class="space-y-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2 rounded-lg bg-primary/10">
              <Coins class="h-6 w-6 text-primary" />
            </div>
            <div>
              <h2 class="text-2xl font-bold">Your Credits</h2>
              <p class="text-sm text-muted-foreground">
                Current credit balance
              </p>
            </div>
          </div>
          <div class="flex items-baseline gap-2">
            <div class="text-4xl font-bold">
              {{ formatCredits(userCredits) }}
            </div>
          </div>
        </div>
      </Card>

      <Tabs :default-value="defaultTab" class="w-full">
        <TabsList class="grid w-full grid-cols-2">
          <TabsTrigger value="packages">
            <Package class="h-4 w-4 mr-2" />
            Resource Packages
          </TabsTrigger>
          <TabsTrigger
            value="individual"
            :disabled="!individualResources?.enabled"
          >
            <Zap class="h-4 w-4 mr-2" />
            Individual Resources
          </TabsTrigger>
        </TabsList>

        <TabsContent value="packages" class="mt-4">
          <Card>
            <div class="p-6">
              <div
                v-if="loading && packages.length === 0"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>

              <div v-else-if="error" class="mb-6">
                <div
                  class="flex items-center gap-3 p-4 rounded-lg bg-destructive/10 border border-destructive/50"
                >
                  <AlertCircle class="h-5 w-5 text-destructive" />
                  <div>
                    <h3 class="font-semibold text-destructive">Error</h3>
                    <p class="text-sm text-muted-foreground">{{ error }}</p>
                  </div>
                </div>
              </div>

              <div
                v-else-if="packages.length === 0"
                class="text-center py-12 text-muted-foreground"
              >
                No packages available
              </div>

              <div
                v-else
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
              >
                <Card
                  v-for="pkg in packages"
                  :key="pkg.id"
                  class="p-6 flex flex-col border-2"
                  :class="{
                    'opacity-60': !canAffordPackage(pkg),
                  }"
                >
                  <div class="flex-1">
                    <!-- Discount Badge -->
                    <div
                      v-if="
                        pkg.final_price !== undefined &&
                        pkg.final_price < pkg.price &&
                        pkg.discount_applied &&
                        pkg.discount_applied > 0
                      "
                      class="mb-3"
                    >
                      <div
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/20 text-green-600 text-xs font-semibold"
                      >
                        {{ pkg.discount_applied.toFixed(0) }}% OFF
                      </div>
                    </div>

                    <h3 class="text-xl font-bold mb-2">{{ pkg.name }}</h3>
                    <p
                      v-if="pkg.description"
                      class="text-sm text-muted-foreground mb-4 line-clamp-2"
                    >
                      {{ pkg.description }}
                    </p>

                    <!-- Price Section -->
                    <div class="mb-4">
                      <div class="flex items-baseline gap-2">
                        <div
                          v-if="
                            pkg.final_price !== undefined &&
                            pkg.final_price < pkg.price
                          "
                        >
                          <div
                            class="text-sm line-through text-muted-foreground"
                          >
                            {{ formatCredits(pkg.price) }}
                          </div>
                          <div class="text-2xl font-bold text-primary">
                            {{ formatCredits(pkg.final_price) }}
                          </div>
                        </div>
                        <div v-else>
                          <div class="text-2xl font-bold text-primary">
                            {{ formatCredits(pkg.price) }}
                          </div>
                        </div>
                        <div class="text-sm text-muted-foreground">Credits</div>
                      </div>
                    </div>

                    <!-- Resources List -->
                    <div v-if="hasResource(pkg)" class="space-y-2 mb-4">
                      <div
                        v-if="pkg.memory_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <HardDrive class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Memory:</span>
                        <span class="font-medium">{{
                          formatBytes(pkg.memory_limit)
                        }}</span>
                      </div>
                      <div
                        v-if="pkg.cpu_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Cpu class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">CPU:</span>
                        <span class="font-medium">{{
                          formatPercentage(pkg.cpu_limit)
                        }}</span>
                      </div>
                      <div
                        v-if="pkg.disk_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Database class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Disk:</span>
                        <span class="font-medium">{{
                          formatBytes(pkg.disk_limit)
                        }}</span>
                      </div>
                      <div
                        v-if="pkg.server_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Server class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Servers:</span>
                        <span class="font-medium">{{ pkg.server_limit }}</span>
                      </div>
                      <div
                        v-if="pkg.database_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Database class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Databases:</span>
                        <span class="font-medium">
                          {{
                            pkg.database_limit === 0 ? "∞" : pkg.database_limit
                          }}
                        </span>
                      </div>
                      <div
                        v-if="pkg.backup_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Database class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Backups:</span>
                        <span class="font-medium">
                          {{ pkg.backup_limit === 0 ? "∞" : pkg.backup_limit }}
                        </span>
                      </div>
                      <div
                        v-if="pkg.allocation_limit > 0"
                        class="flex items-center gap-2 text-sm"
                      >
                        <Server class="h-4 w-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Allocations:</span>
                        <span class="font-medium">
                          {{
                            pkg.allocation_limit === 0
                              ? "∞"
                              : pkg.allocation_limit
                          }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <Button
                    @click="handlePurchase(pkg)"
                    :disabled="purchasing === pkg.id || !canAffordPackage(pkg)"
                    class="w-full"
                    :variant="canAffordPackage(pkg) ? 'default' : 'outline'"
                  >
                    <Loader2
                      v-if="purchasing === pkg.id"
                      class="mr-2 h-4 w-4 animate-spin"
                    />
                    <ShoppingCart v-else class="mr-2 h-4 w-4" />
                    {{
                      purchasing === pkg.id
                        ? "Processing..."
                        : !canAffordPackage(pkg)
                        ? "Insufficient Credits"
                        : "Purchase"
                    }}
                  </Button>
                </Card>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="individual" class="mt-4">
          <Card>
            <div class="p-6">
              <div
                v-if="!individualResources || !individualResources.enabled"
                class="text-center py-12 text-muted-foreground"
              >
                Individual purchases disabled
              </div>

              <div
                v-else-if="individualResources.resources.length === 0"
                class="text-center py-12 text-muted-foreground"
              >
                No individual resources available
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card
                  v-for="resource in individualResources.resources"
                  :key="resource.id"
                  class="p-6 border-2"
                  :class="{
                    'opacity-60': !canAffordResource(resource),
                  }"
                >
                  <!-- Discount Badge -->
                  <div v-if="resource.discount_applied > 0" class="mb-3">
                    <div
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/20 text-green-600 text-xs font-semibold"
                    >
                      {{ resource.discount_applied.toFixed(0) }}% OFF
                    </div>
                  </div>

                  <!-- Header Section -->
                  <div class="mb-4">
                    <div class="flex items-start gap-3 mb-3">
                      <div class="p-2 rounded-lg bg-primary/10">
                        <component
                          :is="getResourceIcon(resource.resource_type)"
                          class="h-5 w-5 text-primary"
                        />
                      </div>
                      <div class="flex-1 min-w-0">
                        <h3 class="text-xl font-bold mb-1">
                          {{ resource.name }}
                        </h3>
                        <p
                          v-if="resource.description"
                          class="text-sm text-muted-foreground line-clamp-2"
                        >
                          {{ resource.description }}
                        </p>
                      </div>
                    </div>

                    <!-- Price Section -->
                    <div class="mb-4">
                      <div class="text-xs text-muted-foreground mb-1">
                        Price per {{ resource.unit }}
                      </div>
                      <div class="flex items-baseline gap-2">
                        <div
                          v-if="resource.discount_applied > 0"
                          class="flex items-baseline gap-2"
                        >
                          <div
                            class="text-sm line-through text-muted-foreground"
                          >
                            {{ formatCredits(resource.price_per_unit) }}
                          </div>
                          <div class="text-2xl font-bold text-primary">
                            {{ formatCredits(resource.final_price_per_unit) }}
                          </div>
                        </div>
                        <div v-else class="text-2xl font-bold text-primary">
                          {{ formatCredits(resource.price_per_unit) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Credits</div>
                      </div>
                    </div>
                  </div>

                  <!-- Purchase Section -->
                  <div class="space-y-3">
                    <div>
                      <Label :for="`amount_${resource.id}`">
                        Amount
                        <span class="text-muted-foreground">
                          (Min: {{ resource.minimum_amount }}
                          <span v-if="resource.maximum_amount">
                            , Max: {{ resource.maximum_amount }} </span
                          >)
                        </span>
                      </Label>
                      <Input
                        :id="`amount_${resource.id}`"
                        v-model.number="individualAmounts[resource.id]"
                        type="number"
                        :min="resource.minimum_amount"
                        :max="resource.maximum_amount || undefined"
                        :placeholder="String(resource.minimum_amount)"
                        class="mt-2"
                      />
                      <p
                        v-if="resource.maximum_amount"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Maximum purchase: {{ resource.maximum_amount }}
                        {{ resource.unit }}
                      </p>
                    </div>

                    <div class="p-3 bg-muted rounded-lg">
                      <div class="text-sm text-muted-foreground">
                        Total Cost
                      </div>
                      <div class="text-xl font-bold">
                        {{ formatCredits(getTotalCost(resource)) }}
                        <span class="text-sm font-normal text-muted-foreground"
                          >Credits</span
                        >
                      </div>
                    </div>

                    <Button
                      @click="handleIndividualPurchase(resource)"
                      :disabled="
                        purchasingIndividual === resource.id ||
                        !canAffordResource(resource)
                      "
                      class="w-full"
                      :variant="
                        canAffordResource(resource) ? 'default' : 'outline'
                      "
                    >
                      <Loader2
                        v-if="purchasingIndividual === resource.id"
                        class="mr-2 h-4 w-4 animate-spin"
                      />
                      <ShoppingCart v-else class="mr-2 h-4 w-4" />
                      {{
                        purchasingIndividual === resource.id
                          ? "Processing..."
                          : !canAffordResource(resource)
                          ? "Insufficient Credits"
                          : "Purchase"
                      }}
                    </Button>
                  </div>
                </Card>
              </div>
            </div>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </div>
</template>
