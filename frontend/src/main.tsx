// vite bundle entry: mounts react islands embedded by php (see data-react-page + props script ids on each page)
import React from "react"
import { createRoot } from "react-dom/client"

import { AdminDashboard, type AdminDashboardProps } from "@/pages/admin-dashboard"
import { EmployeeDashboard, type EmployeeDashboardProps } from "@/pages/employee-dashboard"
import { HrDashboard, type HrDashboardProps } from "@/pages/hr-dashboard"
import { OwnerDashboard, type OwnerDashboardProps } from "@/pages/owner-dashboard"
import { PublicHome, type PublicHomeProps } from "@/pages/public-home"
import "@/styles/global.css"

type PageProps = {
  adminDashboard: AdminDashboardProps
  employeeDashboard: EmployeeDashboardProps
  hrDashboard: HrDashboardProps
  ownerDashboard: OwnerDashboardProps
  publicHome: PublicHomeProps
}

type PageName = keyof PageProps

const pages: { [K in PageName]: React.ComponentType<PageProps[K]> } = {
  adminDashboard: AdminDashboard,
  employeeDashboard: EmployeeDashboard,
  hrDashboard: HrDashboard,
  ownerDashboard: OwnerDashboard,
  publicHome: PublicHome,
}

// props live in a sibling <script type="application/json"> so php can inject server data without inline js objects
function readProps<T>(mount: HTMLElement): T {
  const propsId = mount.dataset.propsId

  if (!propsId) {
    return {} as T
  }

  const propsScript = document.getElementById(propsId)
  const rawProps = propsScript?.textContent?.trim()

  if (!rawProps) {
    return {} as T
  }

  return JSON.parse(rawProps) as T
}

// every [data-react-page] root gets its own root — multiple dashboards can coexist if markup includes several mounts
function bootReactIslands() {
  document.documentElement.classList.add("ams-react-ready")

  document.querySelectorAll<HTMLElement>("[data-react-page]").forEach((mount) => {
    const pageName = mount.dataset.reactPage as PageName | undefined

    if (!pageName || !(pageName in pages)) {
      return
    }

    const Page = pages[pageName] as React.ComponentType<Record<string, unknown>>
    const props = readProps<Record<string, unknown>>(mount)

    createRoot(mount).render(
      <React.StrictMode>
        <Page {...props} />
      </React.StrictMode>,
    )
  })
}

bootReactIslands()
