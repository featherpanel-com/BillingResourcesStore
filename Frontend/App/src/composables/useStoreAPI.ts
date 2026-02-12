import { ref } from "vue";
import axios from "axios";

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  error?: boolean;
  error_message?: string;
  error_code?: string;
}

function extractApiError(err: unknown): string {
  if (axios.isAxiosError(err) && err.response?.data) {
    const d = err.response.data as { error_message?: string; message?: string };
    return d.error_message ?? d.message ?? err.message ?? "An error occurred";
  }
  return err instanceof Error ? err.message : "An error occurred";
}

export interface ResourcePackage {
  id: number;
  name: string;
  description?: string;
  memory_limit: number;
  cpu_limit: number;
  disk_limit: number;
  server_limit: number;
  database_limit: number;
  backup_limit: number;
  allocation_limit: number;
  price: number;
  enabled: boolean;
  sort_order: number;
  discount_percentage?: number;
  discount_start_date?: string | null;
  discount_end_date?: string | null;
  discount_enabled?: boolean;
  original_price?: number;
  final_price?: number;
  discount_applied?: number;
  created_at?: string;
  updated_at?: string;
}

export interface Purchase {
  id: number;
  user_id: number;
  package_id: number;
  package_name?: string;
  price: number;
  memory_limit: number;
  cpu_limit: number;
  disk_limit: number;
  server_limit: number;
  database_limit: number;
  backup_limit: number;
  allocation_limit: number;
  created_at: string;
}

export interface PurchaseResponse {
  package: ResourcePackage;
  resources_added: Record<string, number>;
  credits_remaining: number;
}

export interface PurchasesResponse {
  purchases: Purchase[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    pages: number;
  };
}

export function useStoreAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const getPackages = async (): Promise<ResourcePackage[]> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<{ packages: ResourcePackage[] }>>(
        "/api/user/billingresourcesstore/packages"
      );
      if (response.data.success && response.data.data?.packages) {
        return response.data.data.packages;
      }
      throw new Error(response.data.error_message || "Failed to fetch packages");
    } catch (err) {
      const msg = extractApiError(err);
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const purchasePackage = async (packageId: number): Promise<PurchaseResponse> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post<ApiResponse<PurchaseResponse>>(
        "/api/user/billingresourcesstore/purchase",
        { package_id: packageId }
      );
      if (response.data.success && response.data.data) {
        return response.data.data;
      }
      throw new Error(response.data.error_message || "Failed to purchase package");
    } catch (err) {
      const msg = extractApiError(err);
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const getPurchases = async (page: number = 1, limit: number = 50): Promise<PurchasesResponse> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<PurchasesResponse>>(
        `/api/user/billingresourcesstore/purchases?page=${page}&limit=${limit}`
      );
      if (response.data.success && response.data.data) {
        return response.data.data;
      }
      throw new Error(response.data.error_message || "Failed to fetch purchases");
    } catch (err) {
      const msg = extractApiError(err);
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getPackages,
    purchasePackage,
    getPurchases,
  };
}

